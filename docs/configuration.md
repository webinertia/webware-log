# Configuration Reference

All configuration lives under the `Psr\Log\LoggerInterface::class` key in your application config array. The defaults are:

```php
use Psr\Log\LoggerInterface;

return [
    LoggerInterface::class => [
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
| `channel` | `string` | `'app'` | Monolog channel name. Must match a `LogChannel` enum value (see [Channels](#channels)) |
| `log_errors` | `bool` | `false` | When `true`, the `MezzioErrorHandlerDelegator` wires `MezzioErrorListener` to automatically log uncaught exceptions |
| `process_uuid` | `bool` | `false` | When `true`, pushes `RamseyUuidProcessor` onto the logger to add a UUID v7 to every log record's `extra` data |
| `process_translation` | `bool` | `false` | When `true` and `Laminas\Translator\TranslatorInterface` is in the container, pushes `LaminasI18nProcessor` to translate log messages |
| `table` | `string` | `'log'` | Database table name used by `PhpDbHandler` |

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

The middleware reads the authenticated user from one of two class-name request attributes, preferring webware's contract:

```php
Webware\Core\UserInterface::class          // preferred
Mezzio\Authentication\UserInterface::class
```

These are not configurable. A host attaches its user under whichever of the two contracts it implements, and a package that uses neither simply has no identity to enrich logs with. webware-core is a dev requirement of this package, so a consumer that uses no other webware component does not install it.

## Identity Field

The key the identity is written under in a log record's `extra`, and the key `PhpDbHandler` reads back out of it for the `user_identifier` column, comes from Mezzio's authentication configuration:

```php
return [
    'authentication' => [
        'username' => 'email', // default when absent
    ],
];
```

Change `authentication.username` when your identity column is not `email`; the producer and the consumer both follow it.
