# webware-log

Monolog logging for Mezzio. The package builds a PSR-3 logger from configuration, wires it through
the container, and offers optional enrichment and sinks: authenticated-user identity on every record,
event-driven logging through PSR-14, a file handler, a database handler, and automatic logging of
uncaught exceptions.

This page covers the 2.0 release line.

## Installation

```bash
composer require webware/webware-log
```

`laminas/laminas-component-installer` offers to add `Webware\Log\ConfigProvider` to your config
aggregator. See [Installation](installation.md) for the prompt, the database table, and the SQL
fixture.

## The documents

| Page | What it covers |
|---|---|
| [Installation](installation.md) | Requirements, the config provider prompt, and the database table |
| [Configuration Reference](configuration.md) | Every `Psr\Log\LoggerInterface` config key, the channel enum, and the identity field |
| [Middleware](middleware.md) | Putting `MonologMiddleware` in the pipeline and reading the logger back off the request |
| [Handlers](handlers.md) | `StreamHandler` and `PhpDbHandler`, and how each one is switched on |
| [Processors](processors.md) | `RamseyUuidProcessor` and `LaminasI18nProcessor` |
| [Event-Driven Logging](events.md) | `LogEvent`, the PSR-14 dispatcher wiring, and custom listeners |
| [Error Logging](error-logging.md) | Logging uncaught exceptions without replacing Mezzio's error handler |

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

## The short version

The package registers no handler by default, so a logger it builds discards records until you switch
a sink on. Registering a handler factory in your own config is what turns that sink on; see
[Handlers](handlers.md).

All configuration lives under the `Psr\Log\LoggerInterface::class` key. The defaults are enough to
build a working logger, and every key is optional.
