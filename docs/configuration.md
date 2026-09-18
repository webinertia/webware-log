# Configuration Reference

All configuration lives under the `Psr\Log\LoggerInterface::class` key in your application config array. The defaults are:

```php
use Mezzio\Authentication\UserInterface;
use Psr\Log\LoggerInterface;

return [
    LoggerInterface::class => [
        'auth_attribute'      => UserInterface::class,
        'channel'             => 'app',
        'log_errors'          => false,
        'process_uuid'        => false,
        'process_translation' => false,
        'table'               => 'log',
    ],
];
```

## Options

| Key | Type | Default | Description |
|---|---|---|---|
| `auth_attribute` | `string` | `UserInterface::class` | Request attribute key used to retrieve the authenticated user for log enrichment |
| `channel` | `string` | `'app'` | Monolog channel name. Must match a `LogChannel` enum value (see [Channels](#channels)) |
| `log_errors` | `bool` | `false` | When `true`, the `MezzioErrorHandlerDelegator` wires `MezzioErrorListener` to automatically log uncaught exceptions |
| `process_uuid` | `bool` | `false` | When `true`, pushes `RamseyUuidProcessor` onto the logger to add a UUID v7 to every log record's `extra` data |
| `process_translation` | `bool` | `false` | When `true` and `Laminas\Translator\TranslatorInterface` is in the container, pushes `LaminasI18nProcessor` to translate log messages |
| `table` | `string` | `'log'` | Database table name used by `LaminasDbHandler` |

## Channels

Channels are defined as a `BackedEnum` (`Webware\Log\LogChannel`):

| Case | Value |
|---|---|
| `LogChannel::App` | `app` |
| `LogChannel::Audit` | `audit` |
| `LogChannel::Analytics` | `analytics` |
| `LogChannel::Debug` | `debug` |
| `LogChannel::Error` | `error` |
| `LogChannel::Security` | `security` |
| `LogChannel::System` | `system` |
| `LogChannel::User` | `user` |

Set the channel in config:

```php
use Webware\Log\LogChannel;
use Psr\Log\LoggerInterface;

return [
    LoggerInterface::class => [
        'channel' => LogChannel::Security->value, // 'security'
    ],
];
```

## Authentication Attribute

By default the middleware looks up `UserInterface::class` on the request. If your application uses a different attribute key, override it:

```php
return [
    LoggerInterface::class => [
        'auth_attribute' => 'my_app_user',
    ],
];
```
