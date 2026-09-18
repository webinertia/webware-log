# Project Architecture Blueprint — `webware/webware-log`

> **Current stable:** 0.0.x (branch: `add-phpdb-handler`)  
> **Next target:** 0.1.0 — see [Roadmap](#14-roadmap--version-scope) for planned changes  
> **License:** BSD-3-Clause  
> **PHP requirement:** ~8.4.0 || ~8.5.0 || ~8.6.0

---

## Table of Contents

1. [Architectural Overview](#1-architectural-overview)
2. [Architecture Visualization](#2-architecture-visualization)
3. [Core Architectural Components](#3-core-architectural-components)
4. [Architectural Layers and Dependencies](#4-architectural-layers-and-dependencies)
5. [Data Architecture](#5-data-architecture)
6. [Cross-Cutting Concerns](#6-cross-cutting-concerns)
7. [Technology-Specific Patterns](#7-technology-specific-patterns)
8. [Implementation Patterns](#8-implementation-patterns)
9. [Testing Architecture](#9-testing-architecture)
10. [Deployment Architecture](#10-deployment-architecture)
11. [Extension and Evolution Patterns](#11-extension-and-evolution-patterns)
12. [Architectural Decision Records](#12-architectural-decision-records)
13. [Architecture Governance](#13-architecture-governance)
14. [Roadmap & Version Scope](#14-roadmap--version-scope)

---

## 1. Architectural Overview

`webware-log` is a **PHP logging component** designed to integrate [Monolog v3](https://seldaek.github.io/monolog/) into [Mezzio](https://docs.mezzio.dev/) applications. It acts as a thin, opinionated adapter layer that:

- Exposes PSR-3 (`LoggerInterface`) through the PSR-11 dependency injection container.
- Bridges PSR-14 (`psr/event-dispatcher`) log events to the PSR-3 logger via `phly/phly-event-dispatcher` (0.1.0+).
- Integrates with the Mezzio PSR-15 middleware pipeline to enrich log records with authenticated-user identity.
- Provides Monolog handlers that persist log records to a relational database using either `laminas-db` or `php-db/phpdb`.
- Decorates Mezzio's built-in `ErrorHandler` so that uncaught exceptions are automatically logged.

> **Note — Laminas MVC:** The Laminas team is retiring the MVC framework. All MVC-specific integration (`Runtime::Mvc`, `AbstractController` listener identifiers) was removed in 0.1.0, and the Laminas EventManager bridge listener has since been removed entirely — PSR-14 is the only supported dispatch path.

### Guiding Principles

| Principle | Implementation |
|---|---|
| PSR compliance | PSR-3 logger, PSR-11 container, PSR-14 event dispatcher, PSR-15 middleware |
| Laminas component model | `ConfigProvider` + `laminas-component-installer` |
| Loose coupling | All classes wired via factories; no service-locator anti-pattern inside domain classes |
| Standards-first event bus | PSR-14 (`phly/phly-event-dispatcher`) replaces the non-PSR Laminas EventManager bridge |
| Optional features | i18n translation and UUID enrichment are guarded by config flags and container availability checks |
| Non-invasive integration | `MezzioErrorHandlerDelegator` adds logging without replacing the framework's error handler |
| Config namespacing | Component config is keyed under `LoggerInterface::class` (0.1.0+; was `ConfigProvider::class`) |

### Architectural Boundaries

```
[PSR-15 Middleware Pipeline]
        │
   MonologMiddleware  ──► enriches records with user identity; attaches logger to request
        │
[Monolog\Logger] ──► pushHandler(LaminasDbHandler | PhpDbHandler)
                 └──► pushProcessor([RamseyUuidProcessor], PsrLogMessageProcessor, [LaminasI18nProcessor])
        │
[Database Handler] ──► laminas-db Sql | php-db/phpdb Sql
        │
[MySQL Table: log]

[PSR-14 EventDispatcher] ──► Psr3LogPsr14Listener ──► Monolog\Logger
```

---

## 2. Architecture Visualization

### C4 — Context Diagram

```
┌──────────────────────────────────────────────────────────────────────┐
│  Mezzio Application                                                  │
│                                                                      │
│  ┌─────────────────┐   uses   ┌───────────────────────────────────┐ │
│  │  Application     │ ───────► │  webware/webware-log                │ │
│  │  Code (handlers, │          │  (PSR-3/14/15 logging component)  │ │
│  │  middleware)     │          └───────────────────────────────────┘ │
│  └─────────────────┘                         │                       │
│                                              │ writes                │
│                                   ┌──────────▼──────────┐           │
│                                   │  MySQL Database      │           │
│                                   │  (log table)         │           │
│                                   └─────────────────────┘           │
└──────────────────────────────────────────────────────────────────────┘
```

### C4 — Component Diagram

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  webware/webware-log                                                           │
│                                                                              │
│  ┌──────────────┐   builds   ┌─────────────────────────────────────────┐   │
│  │  LogFactory  │ ──────────► │  Monolog\Logger (PSR-3 LoggerInterface) │   │
│  └──────────────┘             │    • [RamseyUuidProcessor]              │   │
│                               │    • PsrLogMessageProcessor             │   │
│                               │    • [LaminasI18nProcessor]             │   │
│                               │    • LaminasDbHandler | PhpDbHandler    │   │
│                               └─────────────────────────────────────────┘   │
│                                                                              │
│  ┌────────────────────────┐  decorates  ┌──────────────────────────────┐   │
│  │ MezzioErrorHandler     │ ──────────── │  Laminas Stratigility        │   │
│  │ Delegator              │             │  ErrorHandler                 │   │
│  └────────────────────────┘             └──────────────────────────────┘   │
│          │ attaches                                                          │
│  ┌───────▼────────────┐                                                     │
│  │ MezzioErrorListener│  (logs Throwable + request + response)              │
│  └────────────────────┘                                                     │
│                                                                              │
│  ┌──────────────────────┐  implements PSR-15  ┌─────────────────────────┐  │
│  │  MonologMiddleware   │ ──────────────────── │  Mezzio Pipeline        │  │
│  └──────────────────────┘                     └─────────────────────────┘  │
│          │ injects user identity                                             │
│          │ attaches logger to request attribute                              │
│                                                                              │
│  ┌──────────────────────────┐  PSR-14 dispatch  ┌────────────────────────┐ │
│  │  Psr3LogPsr14Listener    │ ─────────────────  │ PSR-14 EventDispatcher │ │
│  │  (0.1.0+)                │                   └────────────────────────┘ │
│  └──────────────────────────┘                                               │
│          │ translates LogEvent → PSR-3 log call                             │
│                                                                              │
│  ┌─────────────────────┐   ┌───────────────────────────────┐               │
│  │  LaminasDbHandler   │   │  PhpDbHandler                 │               │
│  │  (laminas-db Sql)   │   │  (php-db/phpdb Sql)            │               │
│  └─────────────────────┘   └───────────────────────────────┘               │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Data Flow — Request Log Entry

```
HTTP Request
    │
    ▼
MonologMiddleware.process()
    │  pushProcessor(fn: extract UserInterface identity → extra['email'])
    │  request->withAttribute(LoggerInterface, $logger)
    ▼
Handler / Controller
    │  $logger->info('Something happened', $context)
    ▼
Monolog\Logger.log()
    │  → RamseyUuidProcessor    → adds extra['uuid'] (UUID v7)
    │  → PsrLogMessageProcessor → interpolates {placeholders}
    │  → LaminasI18nProcessor   → translates message (optional)
    ▼
LaminasDbHandler | PhpDbHandler .write(LogRecord)
    │  builds INSERT: channel, level, uuid, message, time, user_identifier, [context JSON]
    ▼
MySQL log table
```

### Data Flow — PSR-14 Log Event

```
Handler / Middleware / Service
    │  $dispatcher->dispatch(new LogEvent(Level::Info, LogChannel::User))
    │  ->setMessage('User {name} logged in')->setContext(['name' => $username])
    ▼
phly/phly-event-dispatcher (PSR-14 EventDispatcherInterface)
    ▼
Psr3LogPsr14Listener.__invoke(LogEvent)
    │  extracts: level, message, context, channel
    │  optionally switches logger channel via withName()
    ▼
Monolog\Logger.log()  →  handler chain  →  DB
```

### Data Flow — Uncaught Exception

```
Mezzio ErrorHandler.process()
    │  catches Throwable
    ▼
MezzioErrorListener.__invoke(Throwable, Request, Response)
    │  $logger->error($e->getMessage(), [exception, request, response])
    ▼
Monolog\Logger  →  handler chain  →  DB  (channel: 'error')
```

---

## 3. Core Architectural Components

### 3.1 Enums (`src/LogChannel.php`)

| Enum | Cases | Purpose |
|---|---|---|
| `LogChannel` | `Audit`, `Analytics`, `App`, `Debug`, `Error`, `System`, `User`, `Security` | Type-safe log channel names used throughout the component |

> **0.1.0:** `src/Runtime.php` (`Runtime` enum with `Mezzio` and `Mvc` cases) is deleted. The `Mvc` case is removed as part of Laminas MVC retirement; the `Mezzio` case had no conditional wiring and is no longer needed.

**Design decision:** Using backed string enums makes configuration values refactorable at the call-site without stringly-typed magic strings scattered across consumers.

---

### 3.2 `ConfigProvider` (`src/ConfigProvider.php`)

The root wiring class for the Laminas component installer. When invoked, it returns:

```php
[
    'dependencies'  => [...],   // DI factories, delegators, invokables
    'listeners'     => [...],   // event listener classes to attach
    'templates'     => [...],   // Laminas\View template path
    LoggerInterface::class => [ // component-scoped config key (0.1.0+; was ConfigProvider::class)
        'channel'             => 'app',
        'log_errors'          => false,
        'process_uuid'        => false,
        'process_translation' => false,
        'table'               => 'log',
        'auth_attribute'      => UserInterface::class, // configurable (0.1.0+)
    ],
]
```

**Key pattern (0.1.0+):** component config is namespaced under `LoggerInterface::class` (`Psr\Log\LoggerInterface`) to make the config key directly reflect what it configures and to eliminate any dependency on `ConfigProvider::class` in consuming factories. This is a **breaking change** from 0.0.x where `ConfigProvider::class` was the key.

> **Migration:** In your application config, rename the key `Webware\Log\ConfigProvider::class` to `Psr\Log\LoggerInterface::class`.

---

### 3.3 Container (Factories & Delegators) (`src/Container/`)

| Class | Type | Builds | Notes |
|---|---|---|---|
| `LogFactory` | Factory | `Monolog\Logger` as `LoggerInterface` | Pushes handlers and processors in order |
| `MezzioErrorHandlerDelegator` | Delegator | `Laminas\Stratigility\Middleware\ErrorHandler` | Conditionally attaches `MezzioErrorListener` when `log_errors = true` |

The delegator pattern means the error handler is extended non-destructively — if `log_errors` is `false`, the original handler is returned unmodified.

---

### 3.4 Handlers (`src/Handler/`)

Both handlers extend `Monolog\Handler\AbstractProcessingHandler` and write one row per log record to a relational database table.

| Class | DB Abstraction | Notes |
|---|---|---|
| `LaminasDbHandler` | `laminas/laminas-db` `Sql` | Original implementation; uses `AdapterInterface` |
| `PhpDbHandler` | `php-db/phpdb` `Sql` | Newer handler (current branch); stores `context`+`extra` as JSON; column name is `user_identifier` (vs. `userIdentifier` in Laminas variant) |

**Shared write columns:** `channel`, `level`, `uuid`, `message`, `time`, `user_identifier`  
**PhpDbHandler-only column:** `context` (JSON-encoded merged context + filtered extra)

**Factory pattern:** Each handler has a corresponding `*Factory` class that reads config, resolves the DB adapter from the container, and injects authentication config (`authentication.username`) to know which `extra` key carries the user identity.

---

### 3.5 Event (`src/Event/LogEvent.php`)

**0.0.x:** `LogEvent` extends `Laminas\EventManager\Event` and bridges the Laminas event system to Monolog's level/channel model.

**0.1.0+:** `LogEvent` is refactored to implement `Psr\EventDispatcher\StoppableEventInterface` and no longer extends any Laminas class. It becomes a plain, self-contained value object:

- Implements `isPropagationStopped(): bool` and `stopPropagation(): void`.
- Constructor accepts `LogChannel $channel = LogChannel::App` and `Level $level = Level::Debug` directly.
- Typed accessor methods (`getLevel()`, `getMessage()`, `getChannel()`, `getExtra()`, `getContext()`, `getUuid()`) remain as the public API.
- The fallback to `new ConfigProvider()` in `getChannel()` is removed; the default channel is supplied at construction time.

---

### 3.6 Listeners (`src/Listener/`)

| Class | Trigger mechanism | Responsibility | Status |
|---|---|---|---|
| `MezzioErrorListener` | Attached to Mezzio `ErrorHandler` via delegator | Logs uncaught `Throwable` with request/response context | Active |
| `Psr3LogPsr14Listener` | PSR-14 `EventDispatcherInterface` (0.1.0+) | Bridges `LogEvent` to PSR-3 logger via standards-compliant dispatcher | New in 0.1.0 |

**`Psr3LogPsr14Listener`** (0.1.0+): a callable class that accepts a `LogEvent` directly. Registered with the PSR-14 listener provider for `LogEvent::class`. Reads `level`, `message`, `context`, and `channel` from the event; switches logger channel via `withName()` when the channel differs from `LogChannel::App`.

---

### 3.7 Middleware (`src/Http/Middleware/MonologMiddleware.php`)

A PSR-15 `MiddlewareInterface` that runs early in the Mezzio pipeline to:

1. Extract the authenticated `UserInterface` from the request attributes.
2. Push a closure-based processor that adds the user's identity to every subsequent log record's `extra` array.
3. Attach the configured `LoggerInterface` to the request via `withAttribute()`, making it available to downstream handlers.

---

### 3.8 Processors (`src/Processor/`)

| Class | Monolog Integration | Function |
|---|---|---|
| `RamseyUuidProcessor` | `ProcessorInterface` | Generates a UUID v7 (time-ordered) using the record's `datetime` and stores it in `extra['uuid']` |
| `LaminasI18nProcessor` | `ProcessorInterface` | Translates the log message using an injected `Laminas\Translator\TranslatorInterface`; only registered when the translator is in the container |

---

## 4. Architectural Layers and Dependencies

```
┌────────────────────────────────────────────────┐
│  Framework Integration Layer                   │
│  ConfigProvider, Factories, Delegator          │
│  (depends on: PSR-11, Laminas ServiceManager)  │
├────────────────────────────────────────────────┤
│  Middleware / Listener Layer                   │
│  MonologMiddleware, Psr3LogPsr14Listener,      │
│  MezzioErrorListener                           │
│  (depends on: PSR-14, PSR-15, Monolog Logger)  │
├────────────────────────────────────────────────┤
│  Core Logging Layer                            │
│  Monolog\Logger + Processors + Handlers        │
│  (depends on: monolog/monolog, ramsey/uuid,    │
│   laminas-db | php-db/phpdb)                   │
├────────────────────────────────────────────────┤
│  Domain Primitives                             │
│  LogChannel (enum), LogEvent                   │
│  (no external dependencies)                   │
└────────────────────────────────────────────────┘
```

### Dependency Rules

- Domain primitives have **zero** external dependencies.
- The Core Logging Layer depends only on Monolog and the chosen DB abstraction.
- The Middleware / Listener Layer depends on PSR interfaces and Monolog; it never touches the DB layer directly.
- The Framework Integration Layer is the only layer allowed to reference the PSR-11 container.
- There are **no circular dependencies** within the component.

### Dependency Injection Pattern

All dependencies are injected via constructor injection. No class uses `new` internally for services (except `LogFactory`, which is the composition root for the logger). Service location is limited to factories.

---

## 5. Data Architecture

### Log Table Schema (MySQL)

```sql
CREATE TABLE `log` (
  `id`             int UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid`           char(36)     DEFAULT NULL,
  `channel`        varchar(255) NOT NULL,
  `level`          varchar(9)   NOT NULL,
  `userIdentifier` varchar(320) DEFAULT NULL,  -- LaminasDbHandler
  `user_identifier`varchar(320) DEFAULT NULL,  -- PhpDbHandler (snake_case)
  `message`        longtext     NOT NULL,
  `time`           int UNSIGNED NOT NULL,
  `context`        JSON         DEFAULT NULL,  -- PhpDbHandler only
  PRIMARY KEY (`id`),
  UNIQUE KEY `uuid` (`uuid`),
  KEY `ChannelIndex` (`channel`)
);
```

> **Note:** The `LaminasDbHandler` and `PhpDbHandler` use different column name conventions (`userIdentifier` vs `user_identifier`) and the `context` column is only written by `PhpDbHandler`. Ensure your schema matches the chosen handler.

### Data Flow: Record to Persistence

1. `Monolog\Logger` builds a `LogRecord` value object (immutable).
2. Processors mutate the record (via `$record->with(...)`) to enrich `extra`.
3. The handler receives the fully-processed `LogRecord` and maps its properties to an INSERT statement.
4. The record's `datetime` is stored as a UNIX timestamp integer (`format('U')`).
5. `context` + filtered `extra` are JSON-encoded together when using `PhpDbHandler`.

### UUID Strategy

UUID v7 (time-ordered, monotonic) is used — generated per record using `ramsey/uuid` with the record's own `datetime`. This allows chronological sorting on the `uuid` column while maintaining global uniqueness.

---

## 6. Cross-Cutting Concerns

### 6.1 Error Handling

- **Mezzio pipeline errors:** The `MezzioErrorHandlerDelegator` and `MezzioErrorListener` provide automatic error logging without any application-level code changes.
- **Handler errors:** Failures in `write()` propagate as exceptions. Monolog's `bubble` flag (default `true`) allows errors to continue up the handler stack.
- **Missing dependencies:** `LaminasI18nProcessorFactory` explicitly throws `ServiceNotFoundException` when the translator is absent, failing fast at container build time.

### 6.2 Authentication / User Identity

- `MonologMiddleware` reads `UserInterface` from the PSR-7 request attribute (Mezzio Authentication).
- The user identifier key (`email` by default) is configurable via `authentication.username` in the application config, supporting `mezzio-authentication-session` and other providers.

### 6.3 Internationalization

- `LaminasI18nProcessor` optionally translates log messages before they are written. It is only registered when `TranslatorInterface` is available in the container (checked in `LogFactory`).
- The `process_translation` config flag exists as a semantic marker but the actual guard is the container presence check.

### 6.4 Configuration Management

- All configuration is accessed via the PSR-11 container key `'config'`.
- **0.1.0+:** Component-local config is namespaced under `LoggerInterface::class` (`Psr\Log\LoggerInterface`). This replaces the 0.0.x key of `ConfigProvider::class`.
- External config (authentication, DB adapter) is accessed via well-known conventional keys (`authentication.username`, `AdapterInterface::class`).
- **DB adapter config structure:** `php-db/phpdb` uses its own configuration provider and does **not** share the `laminas-db` configuration structure. Each handler factory resolves only its own adapter FQCN from the container; the host application is responsible for registering the appropriate adapter.

### 6.5 Logging (Meta)

The component itself does not log its own operations. Internal errors propagate as PHP exceptions.

---

## 7. Technology-Specific Patterns

### 7.1 Laminas Component Model

- `ConfigProvider` is the entry point for `laminas-component-installer`.
- The `extra.laminas` key in `composer.json` identifies the component and its config-provider FQCN.
- The component registers itself as a **library** (not an application) — it ships defaults that applications can override.

### 7.2 Mezzio Middleware Pipeline

- `MonologMiddleware` must be placed early in the pipeline (before authentication middleware, or immediately after) so that user identity is available for all downstream log calls.
- The component ships a commented-out `middleware_pipeline` section in `ConfigProvider::getPipelineConfig()` as a reference for application integration.

### 7.3 PSR-14 Event Dispatcher Bridge (0.1.0+)

`phly/phly-event-dispatcher` implements PSR-14. `Psr3LogPsr14Listener` is registered as a listener for `LogEvent::class` with the dispatcher's listener provider.

Applications dispatch log events as:
```php
$dispatcher->dispatch(
    (new LogEvent(Level::Info, LogChannel::User))
        ->setMessage('User {name} logged in')
        ->setContext(['name' => $username])
);
```

The dispatcher calls `Psr3LogPsr14Listener::__invoke(LogEvent)`, which translates the event to a PSR-3 `$logger->log()` call. This is standards-compliant and works with any PSR-14 dispatcher, not just `phly/phly-event-dispatcher`.

### 7.4 Monolog Pipeline

Handler and processor registration order in `LogFactory`:
```
LaminasDbHandler | PhpDbHandler  ← handler (persists to DB)
[RamseyUuidProcessor]            ← processor #1 (UUID v7) — guarded by process_uuid flag (0.1.0+)
PsrLogMessageProcessor           ← processor #2 (interpolate placeholders)
[LaminasI18nProcessor]           ← processor #3 (translate, optional) — guarded by process_translation flag
```
Monolog processes records in **LIFO** order for processors and passes through handler stack in registration order.

### 7.5 PhpDbHandler vs LaminasDbHandler

| Feature | `LaminasDbHandler` | `PhpDbHandler` |
|---|---|---|
| DB abstraction | `laminas/laminas-db` | `php-db/phpdb` |
| Container service ID | `Laminas\Db\Adapter\AdapterInterface::class` | `PhpDb\Adapter\AdapterInterface::class` |
| DB config structure | laminas-db config provider conventions | phpdb config provider conventions (**not shared**) |
| Column: user id | `user_identifier` (0.1.0+; was `userIdentifier`) | `user_identifier` (snake_case) |
| Context storage | Not stored | JSON in `context` column |
| Extra filtering | Passes all extra to `uuid` and auth fields | Filters out `uuid` and auth key before JSON encoding |
| Constructor | `Sql` created once in constructor (0.1.0+; was per-call) | `Sql` instance created once in constructor |
| `parent::__construct()` | Not called | Called with no args |

The `PhpDbHandler` is the current development focus (branch `add-phpdb-handler`).

---

## 8. Implementation Patterns

### 8.1 Adding a New Log Handler

1. Create `src/Handler/MyHandler.php` extending `Monolog\Handler\AbstractProcessingHandler`.
2. Implement `protected function write(LogRecord $record): void`.
3. Create `src/Handler/MyHandlerFactory.php` implementing `__invoke(ContainerInterface): MyHandler`.
4. Register in `ConfigProvider::getDependencies()`:
   ```php
   'factories' => [
       Handler\MyHandler::class => Handler\MyHandlerFactory::class,
   ],
   ```
5. Push the handler in `LogFactory::__invoke()`:
   ```php
   $logger->pushHandler($container->get(Handler\MyHandler::class));
   ```

### 8.2 Adding a New Processor

1. Create `src/Processor/MyProcessor.php` implementing `Monolog\Processor\ProcessorInterface`.
2. `__invoke(LogRecord $record): LogRecord` — enrich `$record->extra` and return `$record->with(extra: ...)`.
3. If container dependencies are needed, create `src/Processor/MyProcessorFactory.php`.
4. Register as `invokables` (no deps) or `factories` (with deps) in `ConfigProvider`.
5. Add `$logger->pushProcessor(...)` in `LogFactory`.

### 8.3 Adding a New Log Channel

Add a case to `LogChannel` enum:
```php
case MyChannel = 'my-channel';
```
Switch the logger's channel at the call-site via:
```php
$logger->withName(LogChannel::MyChannel->value)->info('...');
```
`withName()` creates a clone of the logger with a different channel name.

### 8.4 Triggering a Log via PSR-14 (0.1.0+)

```php
use Webware\Log\Event\LogEvent;
use Webware\Log\LogChannel;
use Monolog\Level;
use Psr\EventDispatcher\EventDispatcherInterface;

$event = (new LogEvent(Level::Info, LogChannel::User))
    ->setMessage('User {name} logged in')
    ->setContext(['name' => $username]);

$dispatcher->dispatch($event);
```

The `EventDispatcherInterface` is resolved from the container (provided by `phly/phly-event-dispatcher`). `Psr3LogPsr14Listener` must be registered with the listener provider for `LogEvent::class`.

---

## 9. Testing Architecture

### Structure

```
test/
├── unit/
│   └── TestAsset/          (shared test doubles — currently empty)
└── integration/
    ├── Extension/
    │   ├── ListenerExtension.php         (PHPUnit bootstrap extension)
    │   ├── IntegrationTestStartedListener.php
    │   └── IntegrationTestStoppedListener.php
    ├── Platform/
    │   ├── FixtureLoader.php             (interface: createDatabase / dropDatabase)
    │   └── MysqlFixtureLoader.php        (PDO-based MySQL fixture loader)
    └── TestFixtures/
        └── mysql.sql                     (DDL for the log table)
```

### Test Strategies

| Suite | Runner config | Purpose |
|---|---|---|
| `unit test` | `./test/unit` | Pure unit tests; no DB or network required |
| `integration test` | `./test/integration` | Full-stack DB tests against a live MySQL container |

### Integration Test Bootstrap

1. `ListenerExtension` registers `IntegrationTestStartedListener` and `IntegrationTestStoppedListener` as PHPUnit event subscribers.
2. On test start: `MysqlFixtureLoader::createDatabase()` runs `mysql.sql` to create the `log` table.
3. On test stop: `MysqlFixtureLoader::dropDatabase()` drops the database.
4. Connection parameters are supplied via environment variables (`TESTS_LAMINAS_DB_MYSQL_ADAPTER_*`).

### Quality Gates

PHPUnit is configured to **fail** on deprecations, notices, and warnings (`failOnDeprecation`, `failOnNotice`, `failOnWarning`), enforcing clean PHP 8.4+ code.

---

## 10. Deployment Architecture

### Docker Development Environment

```yaml
services:
  php:   # PHP 8.3+ container (configurable via PHP_VERSION env)
    volumes:
      - ./:/var/www/html
  mysql: # MySQL 8.0+ container (configurable via MYSQL_VERSION env)
    ports:
      - "3306:3306"
    volumes:
      - ./test/integration/TestFixtures/mysql.sql:/docker-entrypoint-initdb.d/mysql.sql
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
```

The `mysql.sql` fixture is auto-executed by MySQL's `docker-entrypoint-initdb.d` mechanism, so the schema is ready immediately when the container starts.

### Environment Variables

| Variable | Default | Purpose |
|---|---|---|
| `PHP_VERSION` | `8.3.19` | PHP container image version |
| `MYSQL_VERSION` | `8.0.41` | MySQL container image version |
| `MYSQL_DATABASE` | `laminasdb_test` | Test database name |
| `MYSQL_USER` | `user` | MySQL user |
| `MYSQL_PASSWORD` | `password` | MySQL password |
| `MYSQL_RANDOM_ROOT_PASSWORD` | `yes` | Avoids hardcoded root password |
| `TESTS_LAMINAS_DB_MYSQL_ADAPTER_*` | (see phpunit.xml.dist) | Test DSN configuration |

### Runtime Dependency: DB Adapter

This component requires a `Laminas\Db\Adapter\AdapterInterface` (for `LaminasDbHandler`) or `PhpDb\Adapter\AdapterInterface` (for `PhpDbHandler`) to be registered in the application container. These are not shipped with this component — the host application is responsible for configuring the DB adapter.

---

## 11. Extension and Evolution Patterns

### 11.1 Switching from LaminasDbHandler to PhpDbHandler

1. Push `PhpDbHandler` onto the logger in `LogFactory` (optionally remove `LaminasDbHandler`).
2. Update the DB schema: rename `userIdentifier` → `user_identifier` and add a `context JSON` column.
3. Ensure the host application registers `PhpDb\Adapter\AdapterInterface::class` in the container via `php-db/phpdb`'s own config provider — **not** using laminas-db configuration structure; the two config structures are incompatible.
4. Both adapters can coexist in the container under separate FQCN service IDs.

### 11.2 Adding a Non-DB Handler

1. Registering it as a service in the DI container.
2. Pushing it onto the logger in `LogFactory`.

The handler stack in Monolog is ordered — add higher-priority handlers last (they are tried first).

### 11.4 Scoping Processors per Channel

The current `withName()` pattern clones the logger. If per-channel processors are needed, separate `Logger` instances (one per channel) should be maintained in a named service map rather than relying on `withName()`.

---

## 12. Architectural Decision Records

### ADR-001: PSR-3 as the Public API

**Context:** Multiple logging backends exist (Monolog, Laminas\Log, etc.)  
**Decision:** Expose only `Psr\Log\LoggerInterface` from the container; `Monolog\Logger` is an implementation detail.  
**Consequences:** Consumers are decoupled from Monolog. Swapping backends is possible without changing call-sites.

### ADR-002: PSR-14 Event Dispatcher via `phly/phly-event-dispatcher`

**Context:** `Psr3LogLaminasListener` used `Laminas\EventManager` to bridge log events to PSR-3. Laminas EventManager is not PSR-compliant. The Laminas team is retiring Laminas MVC (which was the primary consumer of the shared event manager pattern). PSR-14 provides a standards-compliant, framework-agnostic event dispatch mechanism.  
**Decision:** Add `phly/phly-event-dispatcher` as a `require` dependency and provide `Psr3LogPsr14Listener`. PSR-14 is now the only supported dispatch path: `Psr3LogLaminasListener` was deprecated in 0.1.0 and has been removed, along with `laminas/laminas-eventmanager`.  
**Consequences:** Applications gain a standards-compliant event-driven logging path. Any PSR-14 dispatcher can be substituted. `LogEvent` becomes a plain PHP class implementing `StoppableEventInterface` rather than a Laminas-specific subclass.

### ADR-003: UUID v7 for Record Identification

**Context:** Log records need unique, time-sortable identifiers.  
**Decision:** Use Ramsey UUID v7 (time-ordered), seeded with the record's `datetime`.  
**Consequences:** UUIDs are monotonically increasing within a second, enabling B-tree index efficiency on the `uuid` column. Requires `ramsey/uuid ^4.7`.

### ADR-004: Config Key Migrated to `LoggerInterface::class` (0.1.0)

**Context:** Component config was namespaced under `ConfigProvider::class` (the FQCN of the config provider). This created a subtle coupling where factories had to import `ConfigProvider` solely to use its class name as a string key.  
**Decision:** Change the config key to `Psr\Log\LoggerInterface::class`. This directly names the service being configured, is readable without knowing the internal class, and removes `ConfigProvider` from the dependency list of all factories.  
**Consequences:** Breaking change for applications that override component config. A migration note in the CHANGELOG and README is required. All factory files need updated imports.

### ADR-005: Dual DB Handler Strategy

**Context:** The original `LaminasDbHandler` uses `laminas-db`, which is in security-only maintenance mode under the Laminas team. `php-db/phpdb` ([github.com/php-db/phpdb](https://github.com/php-db/phpdb)) is a community fork that modernizes and improves the architecture of `laminas-db`, actively maintained by its own GitHub org with dedicated driver and adapter packages.  
**Decision:** Provide both handlers; applications choose which to wire in `LogFactory`. Each factory resolves its adapter via its own FQCN service ID (`Laminas\Db\Adapter\AdapterInterface::class` or `PhpDb\Adapter\AdapterInterface::class`). The two adapters have **incompatible configuration structures** and must not share config.  
**Consequences:** Slight code duplication between the two handlers. The `PhpDbHandler` is more feature-complete (stores context JSON, uses snake_case column names). Future consolidation should standardize on `PhpDbHandler` as `laminas-db` is security-only.

### ADR-006: Remove Laminas MVC Integration

**Context:** The Laminas team is retiring the MVC framework. Maintaining `Runtime::Mvc`, `AbstractController` identifiers in `Psr3LogLaminasListener`, and `log_runtime` config adds code surface with no active user base.  
**Decision:** Remove all MVC-specific integration in 0.1.0. `Runtime` enum is deleted entirely. `log_runtime` config key is removed.  
**Consequences:** Any application using the MVC-specific listener identifiers will need to migrate to PSR-14. The component becomes Mezzio-only.

---

## 13. Architecture Governance

### Static Analysis

- **PHPStan** (`phpstan/phpstan` + `phpstan-phpunit`) at strict levels.

### Coding Standards

- **php-cs-fixer** with the `webware/coding-standard` ruleset configured in `.php-cs-fixer.dist.php`.
- Applied rule sets: `@Webware/copyright-header` and `@Webware/coding-standard-1.0`.
- Auto-fixable: run `php-cs-fixer fix` (composer `cs-fix` script).

### CI Quality Gates (`composer check`)

```
cs-check → cs-fix (php-cs-fixer)
static-analysis (phpstan)
test (phpunit unit suite)
```

### Security

- `roave/security-advisories` (dev dependency) prevents installation of packages with known CVEs.
- `renovate.json` is present, indicating automated dependency update PRs via Renovate Bot.

### Branch Strategy

| Branch | Purpose |
|---|---|
| `0.0.x` | Default / stable release branch |
| `add-phpdb-handler` | Active development — adds `PhpDbHandler` (merges to 0.0.x) |
| `0.1.x` _(planned)_ | Target for all 0.1.0 refactoring work |

### Known Technical Debt (0.0.x)

Items marked ✅ are addressed by the [0.1.0 refactoring plan](../plan/refactor-webware-log-0.1.0.md).

| Location | Issue | 0.1.0 |
|---|---|---|
| `LaminasDbHandler` | `Sql` re-created per `write()` call | ✅ TASK-025 |
| `LaminasDbHandler` | Column named `userIdentifier` (camelCase) — inconsistent with `PhpDbHandler` | ✅ TASK-026 |
| `MonologMiddleware` | Hard-coded `UserInterface` attribute key | ✅ TASK-027 |
| `LogEvent::getChannel()` | Instantiates `new ConfigProvider()` at runtime | ✅ TASK-007 |
| `LogFactory` | `process_uuid` / `process_translation` flags not honoured | ✅ TASK-028 |
| `phpunit.xml.dist` | Schema targets PHPUnit 11.4 but requirement is ^13.0 | ✅ TASK-029 |
| `phpcs.xml` | Legacy PHP_CodeSniffer config — tool is not used; style enforced by `php-cs-fixer` | ✅ TASK-031 |
| `psalm.xml.dist` / `psalm-baseline.xml` | Psalm config files — `vimeo/psalm` replaced by PHPStan | ✅ TASK-032 |
| `composer.json` scripts | `cs-check`/`cs-fix` pointed to phpcs/phpcbf; `static-analysis` pointed to `psalm` | ✅ TASK-033 |
| `ConfigProvider` | Config key `ConfigProvider::class` should reflect what it configures | ✅ TASK-001 |
| `Runtime::Mvc` | MVC case defined but never wired; Laminas MVC being retired | ✅ TASK-009 |

---

## 14. Roadmap & Version Scope

### 0.0.x (Current)

- Core PSR-3 / Monolog integration for Mezzio
- `LaminasDbHandler` — persist log records via `laminas-db`
- `PhpDbHandler` — persist log records via `php-db/phpdb` _(branch: `add-phpdb-handler`)_
- `MezzioErrorHandlerDelegator` — automatic error logging
- `MonologMiddleware` — user identity enrichment
- `Psr3LogLaminasListener` — Laminas EventManager → PSR-3 bridge

### 0.1.0 (Planned)

Full scope defined in [plan/refactor-webware-log-0.1.0.md](../plan/refactor-webware-log-0.1.0.md).

| Change | Type | Plan Ref |
|---|---|---|
| Config key: `ConfigProvider::class` → `LoggerInterface::class` | **Breaking** | Phase 1 |
| Remove `Runtime` enum and `log_runtime` config key | **Breaking** | Phase 2 |
| Remove `AbstractController` identifier from `Psr3LogLaminasListener` | Breaking | Phase 2 |
| Deprecate `Psr3LogLaminasListener` | Deprecation | Phase 2 |
| Add `Psr3LogPsr14Listener` + factory (PSR-14 event dispatch) | Feature | Phase 3 |
| Refactor `LogEvent` → implements `StoppableEventInterface` | **Breaking** | Phase 3 |
| Move `phly/phly-event-dispatcher` to `require` | Dependency | Phase 3 |
| Explicit adapter FQCN resolution in both handler factories | Fix | Phase 4 |
| `LaminasDbHandler`: move `Sql` to constructor | Fix | Phase 5 |
| `LaminasDbHandler`: rename column `userIdentifier` → `user_identifier` | **Breaking schema** | Phase 5 |
| `MonologMiddleware`: configurable auth attribute key | Fix | Phase 5 |
| `LogFactory`: honour `process_uuid` / `process_translation` flags | Fix | Phase 5 |
| `phpunit.xml.dist`: fix PHPUnit schema URL to 13.0 | Chore | Phase 5 |
| Delete `phpcs.xml` (PHP_CodeSniffer no longer used) | Chore | Phase 5 |
| Delete `psalm.xml.dist` and `psalm-baseline.xml` (Psalm → PHPStan) | Chore | Phase 5 |
| `composer.json`: remove `vimeo/psalm`; update scripts to `php-cs-fixer` and `phpstan` | Dependency | Phase 5 |

### 0.2.0 (Tentative)

- Evaluate standardizing on `PhpDbHandler` only as `laminas-db` moves further into security-only status
- Install **PCOV** in `docker/php/Dockerfile` for local coverage support; integration tests currently run with `--no-coverage` since coverage is handled in CI pipeline
