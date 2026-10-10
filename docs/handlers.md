# Handlers

Two Monolog handlers are provided for persisting log records to a relational database. Register only the one that matches your application's database adapter.

## LaminasDbHandler

Uses `laminas/laminas-db` to write records.

### Requirements

`Laminas\Db\Adapter\AdapterInterface` must be registered in the PSR-11 container. Refer to the [laminas-db documentation](https://docs.laminas.dev/laminas-db/) for adapter configuration.

### Wiring

`LaminasDbHandler` is registered in the container by `LaminasDbHandlerFactory` and pushed onto the logger by `LogFactory`. No additional wiring is needed beyond providing the adapter in the container.

### Column Mapping

| Log Record Field | DB Column |
|---|---|
| `channel` | `channel` |
| `level_name` | `level` |
| `extra.uuid` | `uuid` |
| `formatted` | `message` |
| `datetime` (unix) | `time` |
| `extra.email` (or custom key) | `user_identifier` |

### Custom Auth Identifier

By default the handler reads `extra.email` for the user column. Override via config:

```php
// Not yet exposed as a top-level config key - override by extending
// LaminasDbHandlerFactory and passing a different $extraAuthIdentifier
// to the LaminasDbHandler constructor.
```

## PhpDbHandler

Uses `php-db/phpdb` to write records. The column mapping and write logic are identical to `LaminasDbHandler`.

### Requirements

`PhpDb\Adapter\AdapterInterface` must be registered via `php-db/phpdb`'s own `ConfigProvider`.

### Choosing Between Handlers

> Do not push both handlers onto the logger simultaneously. `LogFactory` only pushes `LaminasDbHandler` by default. To switch to `PhpDbHandler`, override `LogFactory` in your application config:

```php
use Webware\Log\Handler\PhpDbHandler;
use Psr\Log\LoggerInterface;

// config/autoload/log.local.php
return [
    'dependencies' => [
        'factories' => [
            LoggerInterface::class => MyApp\Container\CustomLogFactory::class,
        ],
    ],
];
```

In `CustomLogFactory`, push `PhpDbHandler` instead of `LaminasDbHandler`.
