# webware-log

Monolog logging for Mezzio. The package builds a PSR-3 logger from configuration, wires it through
the container, and offers optional enrichment and sinks: authenticated-user identity on every record,
event-driven logging through PSR-14, a database handler, and automatic logging of uncaught
exceptions.

This page covers the 1.x release line.

## Installation

```bash
composer require webware/webware-log
```

`laminas/laminas-component-installer` offers to add `Webware\Log\ConfigProvider` to your config
aggregator. See [Installation](installation.md) for the prompt, the database table, and the SQL
fixture.

## Two ways to log

The package supports two logging styles. They are independent, they share one logger, and a host may
use either or both.

**Direct logging.** Inject `Psr\Log\LoggerInterface` and call `$logger->info(...)`,
`$logger->error(...)`, and so on. `LogFactory` builds that logger from the
`Psr\Log\LoggerInterface::class` config section, so every consumer receives the same configured
instance. This is the ordinary PSR-3 path: it is what [Middleware](middleware.md) enriches with the
authenticated user and what the [Handlers](handlers.md) write.

**Event-driven logging.** Dispatch a `LogEvent` through the PSR-14 dispatcher and let
`Psr3LogPsr14Listener` write it to that same logger, taking the channel from the event. The class
that raises the event depends on the dispatcher and not on PSR-3, which is the reason to choose this
path. See [Event-Driven Logging](events.md).

Configuration, processors and handlers apply to both, because both end at the same logger. The
choice is about coupling rather than about behaviour.

## The documents

| Page | What it covers |
|---|---|
| [Installation](installation.md) | Requirements, the config provider prompt, and the database table |
| [Configuration Reference](configuration.md) | Every `Psr\Log\LoggerInterface` config key, the channel enum, and the authentication attribute |
| [Middleware](middleware.md) | Putting `MonologMiddleware` in the pipeline and reading the logger back off the request |
| [Handlers](handlers.md) | `PhpDbHandler` and how it is switched on |
| [Processors](processors.md) | `RamseyUuidProcessor` and `LaminasI18nProcessor` |
| [Event-Driven Logging](events.md) | `LogEvent`, the PSR-14 dispatcher wiring, custom listeners, and the Laminas EventManager bridge |
| [Error Logging](error-logging.md) | Logging uncaught exceptions without replacing Mezzio's error handler |

## The short version

`Webware\Log\ConfigProvider` registers `Webware\Log\Handler\PhpDbHandler`, and `LogFactory` pushes it
onto the logger. Its factory resolves `PhpDb\Adapter\AdapterInterface` from the container, so an
application that installs `php-db/phpdb` gets database logging without further wiring. See
[Handlers](handlers.md).

All configuration lives under the `Psr\Log\LoggerInterface::class` key. The defaults are enough to
build a working logger, and every key is optional.
