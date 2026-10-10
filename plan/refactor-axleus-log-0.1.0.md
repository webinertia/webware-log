---
goal: Refactor webware-log to v0.1.0 - PSR-14 support, config key migration, MVC removal, dual DB adapter, and technical debt resolution
version: 0.1.0
date_created: 2026-05-01
last_updated: 2026-05-02
owner: webware
status: 'In Progress'
tags: [refactor, architecture, feature, chore]
---

# Introduction

![Status: In Progress](https://img.shields.io/badge/status-In%20Progress-yellow)

This plan covers all changes targeted for the `0.1.0` release of `webware/webware-log`. The changes fall into five areas:

1. **Config key migration**: replace `ConfigProvider::class` as the top-level config array key with `LoggerInterface::class`.
2. **Laminas MVC removal**: remove all MVC-specific integration code since the Laminas team is retiring the MVC framework.
3. **PSR-14 event dispatcher support**: add `phly/phly-event-dispatcher` to bridge log events via the standards-compliant PSR-14 dispatcher alongside (and eventually replacing) the non-PSR-compliant Laminas EventManager bridge.
4. **Dual DB adapter resolution**: consolidate factory container resolution so both `laminas-db` and `php-db/phpdb` adapters are resolved via `AdapterInterface::class`, with explicit documentation of phpdb's different configuration structure.
5. **Technical debt resolution**: fix all known `// todo` items and configuration inconsistencies identified in the v0.0.x codebase.

---

## 1. Requirements & Constraints

- **REQ-001**: The top-level config key used to namespace component config must be changed from `ConfigProvider::class` to `LoggerInterface::class` (`Psr\Log\LoggerInterface`) in every factory and delegator that reads it.
- **REQ-002**: A PSR-14 (`psr/event-dispatcher`) compliant log listener must be added, implemented via `phly/phly-event-dispatcher`.
- **REQ-003**: `LogEvent` must be refactored to implement `Psr\EventDispatcher\StoppableEventInterface` and must no longer extend `Laminas\EventManager\Event`.
- **REQ-004**: All Laminas MVC-specific code must be removed: `Runtime::Mvc` enum case, `AbstractController` identifier in `Psr3LogLaminasListener`, and the `log_runtime` config key.
- **REQ-005**: Both `LaminasDbHandler` and `PhpDbHandler` factories must resolve the DB adapter exclusively via `$container->get(AdapterInterface::class)` using their respective imported FQCN. No adapter-specific config key resolution logic beyond that.
- **REQ-006**: phpdb (`php-db/phpdb`) does **not** share the same configuration structure as `laminas-db`. The `PhpDbHandlerFactory` must not attempt to read laminas-db-style config keys. Each factory only reads config scoped to its own adapter's conventions.
- **REQ-007**: `LaminasDbHandler::write()` must not instantiate `Sql` on every call. The `Sql` instance must be created once in the constructor.
- **REQ-008**: `MonologMiddleware` must detect which authentication config key to use rather than relying on a hard-coded `UserInterface` attribute name.
- **REQ-009**: `LogEvent::getChannel()` must not instantiate `new ConfigProvider()` at runtime. The default channel must be injected or resolved at construction time.
- **REQ-010**: `LogFactory` must honour the `process_uuid` and `process_translation` boolean config flags before pushing processors.
- **CON-001**: `laminas/laminas-eventmanager` remains a `require-dev` dependency (test use only) after MVC removal. Do not promote it to `require`.
- **CON-002**: `phly/phly-event-dispatcher` must be added as a `require` dependency (not dev-only) because the PSR-14 listener ships in `src/`.
- **CON-003**: `psr/event-dispatcher` is a transitive dependency of `phly/phly-event-dispatcher`; do not add it explicitly to `composer.json`.
- **CON-004**: Breaking changes in config key and event system are acceptable for a minor version bump (0.0.x → 0.1.0). A CHANGELOG entry and migration note in the README are required.
- **GUD-001**: All new classes must follow the `webware/coding-standard` rules enforced by `.php-cs-fixer.dist.php` (`@Webware/coding-standard-1.0` rule set via `php-cs-fixer`).
- **GUD-002**: All new factories must follow the existing pattern: read `$config[LoggerInterface::class]` from the container's `config` service.
- **PAT-001**: Constructor injection only - no service-locator usage inside domain classes.
- **PAT-002**: PSR-14 listener must be a standalone callable class, not a closure, to allow container resolution and testing.

---

## 2. Implementation Steps

### Implementation Phase 1: Config Key Migration

- **GOAL-001**: Replace `ConfigProvider::class` with `LoggerInterface::class` as the top-level component config key in all source files.

| Task | Description | Completed | Date |
|------|-------------|-----------|------|
| TASK-001 | In `src/ConfigProvider.php`: change the key `static::class` (which resolves to `ConfigProvider::class`) in the `__invoke()` return array to `\Psr\Log\LoggerInterface::class`. Add `use Psr\Log\LoggerInterface;` import. | ✅ | 2026-05-02 |
| TASK-002 | In `src/ConfigProvider.php`: rename the method `getDefaultConfig()` to `getConfigDefaults()` to reflect the new semantics and update the `__invoke()` call accordingly. | ✅ | 2026-05-02 |
| TASK-003 | In `src/Container/LogFactory.php`: replace every reference to `$config[ConfigProvider::class]` with `$config[LoggerInterface::class]`. Update imports: remove `use Webware\Log\ConfigProvider;`, add `use Psr\Log\LoggerInterface;`. | ✅ | 2026-05-02 |
| TASK-004 | In `src/Container/MezzioErrorHandlerDelegator.php`: replace `$container->get('config')[ConfigProvider::class]` with `$container->get('config')[LoggerInterface::class]`. Update imports accordingly. | ✅ | 2026-05-02 |
| TASK-005 | In `src/Handler/LaminasDbHandlerFactory.php`: replace `$config[ConfigProvider::class]` with `$config[LoggerInterface::class]`. Update imports. | ✅ | 2026-05-02 |
| TASK-006 | In `src/Handler/PhpDbHandlerFactory.php`: replace `$config[ConfigProvider::class]` with `$config[LoggerInterface::class]`. Update imports. | ✅ | 2026-05-02 |
| TASK-007 | In `src/Event/LogEvent.php`: remove the `getChannel()` fallback that calls `(new ConfigProvider())->getWebwareConfig()['channel']`. Inject the default `LogChannel` via the constructor instead (add a `defaultChannel` constructor parameter defaulting to `LogChannel::App`). Remove `use Webware\Log\ConfigProvider;` import. | ✅ | 2026-05-02 |
| TASK-008 | Update `test/` files and any test fixtures that reference the `ConfigProvider::class` config key to use `LoggerInterface::class`. | ✅ | 2026-05-02 |

---

### Implementation Phase 2: Remove Laminas MVC Integration

- **GOAL-002**: Excise all Laminas MVC-specific code, leaving only Mezzio (PSR-15) integration.

| Task | Description | Completed | Date |
|------|-------------|-----------|------|
| TASK-009 | Delete `src/Runtime.php`. The `Runtime` enum's only remaining intended case (`Mezzio`) is no longer needed once MVC is removed; runtime is always Mezzio. | ✅ | 2026-05-02 |
| TASK-010 | In `src/ConfigProvider.php`: remove the `'log_runtime' => Runtime::Mezzio->value` entry from the `__invoke()` return array. Remove `use Webware\Log\Runtime;` import. | ✅ | 2026-05-02 |
| TASK-011 | In `src/Listener/Psr3LogLaminasListener.php`: remove `Laminas\Mvc\Controller\AbstractController` from the `$identifiers` array and the corresponding `use` import. The listener now only attaches to `MiddlewareInterface` and `RequestHandlerInterface` identifiers. | ✅ | 2026-05-02 |
| TASK-012 | Evaluate whether `Psr3LogLaminasListener` should be kept at all given PSR-14 adoption (Phase 3). If the PSR-14 listener fully replaces it, mark `Psr3LogLaminasListener` as `@deprecated` with a note pointing to the PSR-14 equivalent. Full removal is a candidate for 0.2.0. | ✅ | 2026-05-02 |
| TASK-013 | Remove `Laminas\Mvc\Controller\AbstractController` from `composer.json` suggestions or dev dependencies if it was added explicitly. Confirm `laminas/laminas-eventmanager` stays in `require-dev` only. | ✅ | 2026-05-02 |

---

### Implementation Phase 3: PSR-14 Event Dispatcher Support

- **GOAL-003**: Add a standards-compliant PSR-14 log event and listener using `phly/phly-event-dispatcher`.

| Task | Description | Completed | Date |
|------|-------------|-----------|------|
| TASK-014 | Add `phly/phly-event-dispatcher: ^1.5.0` to `require` (not `require-dev`) in `composer.json`. | ✅ | 2026-05-02 |
| TASK-015 | Refactor `src/Event/LogEvent.php`: remove `extends Laminas\EventManager\Event`, add `implements Psr\EventDispatcher\StoppableEventInterface`. Add `private bool $propagationStopped = false` property. Implement `isPropagationStopped(): bool` and `stopPropagation(): void`. Keep all existing typed accessor methods (`setLevel`, `getMessage`, etc.) as they form the public API. Remove the `Laminas\EventManager\Event` import and parent constructor call. | ✅ | 2026-05-02 |
| TASK-016 | Create `src/Listener/Psr3LogPsr14Listener.php`. This class: accepts `LoggerInterface` via constructor injection; implements a PSR-14-compatible `__invoke(LogEvent $event): void` method that reads `level`, `message`, `context`, `channel` from the event and calls `$this->logger->log(...)`, switching channel via `withName()` when needed. | ✅ | 2026-05-02 |
| TASK-017 | Create `src/Listener/Psr3LogPsr14ListenerFactory.php`. Reads `LoggerInterface` from the container and constructs `Psr3LogPsr14Listener`. | ✅ | 2026-05-02 |
| TASK-018 | Register `Psr3LogPsr14Listener` in `ConfigProvider::getDependencies()` under `factories`. | ✅ | 2026-05-02 |
| TASK-019 | Update `ConfigProvider::getListeners()` to return `Psr3LogPsr14Listener::class` alongside (or in place of) the Laminas listener, coordinating with TASK-012. | ✅ | 2026-05-02 |
| TASK-020 | Update `src/Event/LogEvent.php` constructor signature: remove the `Level $name` Monolog parameter; replace with `LogChannel $channel = LogChannel::App` and `Level $level = Level::Debug`. The event name for PSR-14 purposes is the FQCN of the event class itself (no string event name needed). | ✅ | 2026-05-02 |

---

### Implementation Phase 4: Dual DB Adapter Resolution

- **GOAL-004**: Ensure both `LaminasDbHandler` and `PhpDbHandler` factories correctly resolve their respective `AdapterInterface` from the PSR-11 container without cross-contamination of config structures.

| Task | Description | Completed | Date |
|------|-------------|-----------|------|
| TASK-021 | In `src/Handler/LaminasDbHandlerFactory.php`: ensure the adapter is resolved as `$container->get(\Laminas\Db\Adapter\AdapterInterface::class)`. Document with an inline comment that laminas-db registers its adapter under this FQCN service ID. | ✅ | 2026-05-02 |
| TASK-022 | In `src/Handler/PhpDbHandlerFactory.php`: ensure the adapter is resolved as `$container->get(\PhpDb\Adapter\AdapterInterface::class)`. Add an inline comment: _"phpdb does not share laminas-db's configuration structure. The adapter is wired independently by the host application under the PhpDb FQCN service ID."_ | ✅ | 2026-05-02 |
| TASK-023 | In `src/Handler/LaminasDbHandlerFactory.php`: remove the cast `$adapter = $container->get(AdapterInterface::class)` and replace with the fully-qualified `\Laminas\Db\Adapter\AdapterInterface::class` constant to eliminate any ambiguity when both adapters are in the container. | ✅ | 2026-05-02 |
| TASK-024 | Add a `README` section (or update the existing one) titled "DB Adapter Configuration" that explains: (a) the host application must register the adapter it intends to use; (b) `php-db/phpdb` uses its own config provider separate from `laminas-db`; (c) only the corresponding handler should be pushed onto the logger in `LogFactory`. | ✅ | 2026-05-02 |

---

### Implementation Phase 5: Technical Debt Resolution

- **GOAL-005**: Resolve all `// todo` items and known inconsistencies identified in v0.0.x.

| Task | Description | Completed | Date |
|------|-------------|-----------|------|
| TASK-025 | `src/Handler/LaminasDbHandler.php`: move `$sql = new Sql($this->adapterInterface, $this->table)` from `write()` into the constructor. Declare `private readonly Sql $sql` property. Remove the per-call instantiation. | | |
| TASK-026 | `src/Handler/LaminasDbHandler.php`: rename column `userIdentifier` to `user_identifier` (snake_case) to align with `PhpDbHandler` and update `test/integration/TestFixtures/mysql.sql` fixture accordingly. This is a **breaking schema change**: document in CHANGELOG. | | |
| TASK-027 | `src/Middleware/MonologMiddleware.php`: resolve the `UserInterface` attribute key by reading `$config[LoggerInterface::class]['auth_attribute']` with a fallback to `Mezzio\Authentication\UserInterface::class`. Inject config via constructor. Create `MonologMiddlewareFactory` update to pass config. | | |
| TASK-028 | `src/Container/LogFactory.php`: wrap `$logger->pushProcessor($uuidProcessor)` in an `if ($config['process_uuid'])` guard. Wrap the `LaminasI18nProcessor` push in an `if ($config['process_translation'])` guard (the existing container check can remain as an additional guard). | | |
| TASK-029 | `phpunit.xml.dist`: update `xsi:noNamespaceSchemaLocation` from `https://schema.phpunit.de/11.4/phpunit.xsd` to `https://schema.phpunit.de/13.0/phpunit.xsd` to match the `phpunit/phpunit: ^13.0` requirement. | | |
| TASK-030 | `src/ConfigProvider.php`: add default values for the new `process_uuid` and `process_translation` flags to the `getLoggerConfig()` return array to ensure they are always present with documented defaults. | | |
| TASK-031 | Delete `phpcs.xml` from the repository root. PHP_CodeSniffer is no longer used; all code style enforcement is via `php-cs-fixer` with `.php-cs-fixer.dist.php`. | ✅ | 2026-05-02 |
| TASK-032 | Delete `psalm.xml.dist` and `psalm-baseline.xml` from the repository root. Psalm (`vimeo/psalm`) is replaced by PHPStan as the sole static analysis tool. | ✅ | 2026-05-02 |
| TASK-033 | `composer.json`: remove `vimeo/psalm` and `psalm/plugin-phpunit` from `require-dev`; update `scripts.cs-check` to `php-cs-fixer fix --dry-run --diff`, `scripts.cs-fix` to `php-cs-fixer fix`, and `scripts.static-analysis` to `phpstan analyse`. | ✅ | 2026-05-02 |

---

## 3. Alternatives

- **ALT-001**: Keep `ConfigProvider::class` as the config key and add `LoggerInterface::class` as an alias. Rejected - maintaining two keys adds complexity and the goal is a clean migration.
- **ALT-002**: Keep `Runtime` enum and support MVC conditionally via feature flags. Rejected - the Laminas team is retiring MVC; continued support would be maintenance burden with no upstream future.
- **ALT-003**: Replace `Psr3LogLaminasListener` entirely in 0.1.0 rather than deprecating. Rejected - some host applications may still dispatch via `Laminas\EventManager` in Mezzio contexts. Deprecation in 0.1.0 with full removal in 0.2.0 is safer.
- **ALT-004**: Provide a single `DbHandlerFactory` that auto-detects the available adapter. Rejected - the two adapters have different config structures (phpdb does not use laminas-db config); auto-detection would silently select the wrong adapter. Explicit factories are unambiguous.
- **ALT-005**: Use `ramsey/uuid` UUID v4 instead of v7. Rejected - UUID v7 is time-ordered, which improves B-tree index write performance on the `uuid` column.

---

## 4. Dependencies

- **DEP-001**: `phly/phly-event-dispatcher: ^1.5.0` - move from `require-dev` to `require`.
- **DEP-002**: `psr/event-dispatcher` - transitively required by `phly/phly-event-dispatcher`; provides `Psr\EventDispatcher\StoppableEventInterface` and `Psr\EventDispatcher\EventDispatcherInterface`.
- **DEP-003**: `laminas/laminas-eventmanager: ^3.14` - remains in `require-dev` only; used in tests and for the deprecated `Psr3LogLaminasListener`.
- **DEP-004**: `php-db/phpdb` and associated driver packages - must be registered in the host application container. Not a direct `composer.json` dependency of this component.
- **DEP-005**: `laminas/laminas-db` - must be registered in the host application container when `LaminasDbHandler` is used. Not a direct `composer.json` dependency of this component.
- **DEP-006**: Remove `vimeo/psalm` and `psalm/plugin-phpunit` from `require-dev`; update `scripts.static-analysis` from `"psalm --shepherd --stats"` to `"phpstan analyse"`. Psalm is replaced by PHPStan as the sole static analysis tool.

---

## 5. Files

- **FILE-001**: `src/ConfigProvider.php` - config key migration, `log_runtime` removal, listener registration update, method rename.
- **FILE-002**: `src/Runtime.php` - **deleted**.
- **FILE-003**: `src/Event/LogEvent.php` - refactored: remove Laminas EM inheritance, implement PSR-14 `StoppableEventInterface`, remove `new ConfigProvider()` call, update constructor.
- **FILE-004**: `src/Container/LogFactory.php` - config key migration, `process_uuid`/`process_translation` flag guards.
- **FILE-005**: `src/Container/MezzioErrorHandlerDelegator.php` - config key migration.
- **FILE-006**: `src/Handler/LaminasDbHandler.php` - move `Sql` to constructor, rename column `userIdentifier` → `user_identifier`.
- **FILE-007**: `src/Handler/LaminasDbHandlerFactory.php` - config key migration, explicit `\Laminas\Db\Adapter\AdapterInterface::class` resolution.
- **FILE-008**: `src/Handler/PhpDbHandlerFactory.php` - config key migration, explicit `\PhpDb\Adapter\AdapterInterface::class` resolution, inline comment on config structure.
- **FILE-009**: `src/Listener/Psr3LogLaminasListener.php` - remove `AbstractController` identifier; add `@deprecated` annotation.
- **FILE-010**: `src/Listener/Psr3LogPsr14Listener.php` - **new file**.
- **FILE-011**: `src/Listener/Psr3LogPsr14ListenerFactory.php` - **new file**.
- **FILE-012**: `src/Middleware/MonologMiddleware.php` - configurable auth attribute key; inject config.
- **FILE-013**: `src/Middleware/MonologMiddlewareFactory.php` - pass config to `MonologMiddleware`.
- **FILE-014**: `test/integration/TestFixtures/mysql.sql` - rename `userIdentifier` column to `user_identifier`.
- **FILE-015**: `phpunit.xml.dist` - update PHPUnit schema URL to 13.0.
- **FILE-016**: `composer.json` - move `phly/phly-event-dispatcher` from `require-dev` to `require`.
- **FILE-017**: `docs/Project_Architecture_Blueprint.md` - update for 0.1.0 scope (separate update, see blueprint).
- **FILE-018**: `phpcs.xml` - **deleted**. PHP_CodeSniffer is no longer used; code style is enforced entirely by `.php-cs-fixer.dist.php`.
- **FILE-019**: `psalm.xml.dist` - **deleted**. Psalm is replaced by PHPStan as the sole static analysis tool.
- **FILE-020**: `psalm-baseline.xml` - **deleted**. Psalm baseline is no longer relevant after removing `vimeo/psalm`.
- **FILE-021**: `src/Container/ListenerProviderAggregateFactory.php` - **new file**. Builds `ListenerProviderAggregate` from `'listeners'` and `'listener_providers'` config keys, compatible with `webware/commandbus-event` conventions.
- **FILE-022**: `test/unit/ConfigProviderTest.php` - extended with PSR-14 alias, listener key, and `getListeners()` format assertions.

---

## 6. Testing

- **TEST-001**: Unit test for `LogEvent` - verify `isPropagationStopped()` returns `false` by default; returns `true` after `stopPropagation()`; all accessor methods return correct values. ✅ `test/unit/Event/LogEventTest.php`
- **TEST-002**: Unit test for `Psr3LogPsr14Listener` - verify `__invoke(LogEvent)` calls `$logger->log()` with the correct level, message, and context; verify `withName()` is called when the channel differs from `LogChannel::App`; verify renamed logger is used for the actual `log()` call. ✅ `test/unit/Listener/Psr3LogPsr14ListenerTest.php`
- **TEST-003**: Unit test for `LogFactory` - verify that when `process_uuid = false`, `RamseyUuidProcessor` is NOT pushed; when `process_translation = false`, `LaminasI18nProcessor` is NOT pushed.
- **TEST-004**: Unit test for `LaminasDbHandlerFactory` and `PhpDbHandlerFactory` - verify each resolves its own adapter FQCN and reads config under `LoggerInterface::class`.
- **TEST-005**: Unit test for `MonologMiddleware` - verify the auth attribute key is read from config, with correct fallback to `UserInterface::class`.
- **TEST-006**: Integration test for `LaminasDbHandler` - verify the `user_identifier` column (snake_case) is populated correctly after the column rename.
- **TEST-007**: Integration test for `PhpDbHandler` - verify `context` JSON column is populated and `user_identifier` is set.
- **TEST-008**: Verify static analysis (`phpstan`) passes with zero new errors on all modified files.
- **TEST-009**: Verify `php-cs-fixer --dry-run` reports no violations on all new and modified files against the `.php-cs-fixer.dist.php` ruleset (`@Webware/coding-standard-1.0`).

---

## 7. Risks & Assumptions

- **RISK-001**: Renaming the config key from `ConfigProvider::class` to `LoggerInterface::class` is a **breaking change** for any host application that has overridden component config using the old key. Mitigation: document clearly in CHANGELOG and README migration guide.
- **RISK-002**: Renaming the `userIdentifier` column to `user_identifier` is a **breaking schema change**. Any existing production databases using `LaminasDbHandler` will require a migration. Mitigation: provide a DDL migration snippet in the CHANGELOG.
- **RISK-003**: Moving `phly/phly-event-dispatcher` to `require` increases the component's installation footprint. Mitigation: the package is lightweight and has minimal transitive dependencies.
- **RISK-004**: Deprecating `Psr3LogLaminasListener` may break host applications that register it via the `listeners` config key. Mitigation: the listener continues to function in 0.1.0; removal is 0.2.0.
- **RISK-005**: `phpdb` config structure is assumed to differ from `laminas-db`. The exact phpdb config schema must be confirmed against `php-db/phpdb` source before TASK-022 is finalized.
- **ASSUMPTION-001**: Host applications that use `PhpDbHandler` register `PhpDb\Adapter\AdapterInterface::class` as the container service ID for the phpdb adapter.
- **ASSUMPTION-002**: Host applications that use `LaminasDbHandler` register `Laminas\Db\Adapter\AdapterInterface::class` as the container service ID for the laminas-db adapter.
- **ASSUMPTION-003**: `phly/phly-event-dispatcher` dispatches events by calling all registered listeners for the event's FQCN, consistent with PSR-14.
