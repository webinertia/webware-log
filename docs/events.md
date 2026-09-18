# Event-Driven Logging (PSR-14)

This package integrates with `phly/phly-event-dispatcher` to support event-driven logging via PSR-14. Dispatching a `LogEvent` routes through the listener provider aggregate to `Psr3LogPsr14Listener`, which writes to the Monolog logger.

## Dispatching a Log Event

```php
use Webware\Log\Event\LogEvent;
use Webware\Log\LogChannel;
use Monolog\Level;
use Psr\EventDispatcher\EventDispatcherInterface;

// $dispatcher is resolved from the container
$event = (new LogEvent(LogChannel::App, Level::Info))
    ->setMessage('User registered')
    ->setContext(['user_id' => 42]);

$dispatcher->dispatch($event);
```

## LogEvent API

| Method | Description |
|---|---|
| `__construct(LogChannel $channel, Level $level)` | Set the channel and level at construction |
| `setMessage(string $message): static` | Set the log message |
| `getMessage(): string` | Get the log message |
| `setContext(array $context): static` | Set PSR-3 context data |
| `getContext(): array` | Get context data |
| `setExtra(array $extra): static` | Set Monolog extra data |
| `getExtra(): array` | Get extra data |
| `getChannel(): LogChannel` | Get the channel |
| `getLevel(): Level` | Get the Monolog level |
| `isPropagationStopped(): bool` | PSR-14 propagation control |

### Level Constants

`LogEvent` exposes string constants mirroring PSR-3 log levels for convenience:

```php
LogEvent::EVENT_LOG_DEBUG     // 'debug'
LogEvent::EVENT_LOG_INFO      // 'info'
LogEvent::EVENT_LOG_WARNING   // 'warning'
LogEvent::EVENT_LOG_ERROR     // 'error'
LogEvent::EVENT_LOG_CRITICAL  // 'critical'
LogEvent::EVENT_LOG_ALERT     // 'alert'
LogEvent::EVENT_LOG_EMERGENCY // 'emergency'
```

## Adding Custom Listeners

Merge additional listeners into your application config under the `listeners` key:

```php
use Webware\Log\ConfigProvider;
use Webware\Log\Event\LogEvent;

return [
    ConfigProvider::LISTENER_KEY => [
        LogEvent::class => [
            ['listener' => MyApp\Log\AuditListener::class, 'priority' => 10],
        ],
    ],
];
```

Higher priority values run first. The built-in `Psr3LogPsr14Listener` is registered at priority `1`.
