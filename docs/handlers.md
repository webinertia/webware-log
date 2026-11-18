# Handlers

`webware-log` registers no Monolog handler by default, so a logger it builds has no sinks and discards records until one is wired. This is deliberate: registering a database handler on every consumer's behalf would make a database a requirement of a package that may be used without one.

`LogFactory` attaches a handler when the container has one under a key it knows:

| Container key | Handler |
|---|---|
| `Monolog\Handler\StreamHandler::class` | `Webware\Log\Handler\StreamHandlerFactory` |
| `Webware\Log\Handler\PhpDbHandler::class` | `Webware\Log\Handler\PhpDbHandlerFactory` |

Both are opt-in, and a logger may carry both: one record then reaches the file and the table. Registering the factory is what turns a sink on.

## StreamHandler

Writes records to a file under the application's `data` directory e.g. to `data/log/{channel}.log`.

### Requirements

Only `monolog/monolog`, which is already a dependency. There is no external service and no adapter to register, so this handler works in a console application and a web application alike. Both are expected to run with the application root as the working directory, which is what makes `data/log` resolve to the same place in each.

### Wiring

```php
// config/autoload/log.local.php
use Monolog\Handler\StreamHandler;
use Webware\Log\Handler\StreamHandlerFactory;

return [
    'dependencies' => [
        'factories' => [
            StreamHandler::class => StreamHandlerFactory::class,
        ],
    ],
];
```

`StreamHandlerFactory` builds the path from the `logFile` config key and the configured channel, substituting `{channel}`. The default is `data/log/{channel}.log`, so the default configuration writes to `data/log/app.log`.

```php
// config/autoload/log.local.php
use Psr\Log\LoggerInterface;

return [
    LoggerInterface::class => [
        'channel'  => 'security',                   // data/log/security.log
        'logFile'  => 'data/log/{channel}.log',     // the default
    ],
];
```

`Monolog\Handler\StreamHandler` creates the directory and the file on the first write, so nothing has to exist beforehand and `data/log` does not need to be committed.

## PhpDbHandler

Uses `php-db/phpdb` to write records.

### Requirements

- `php-db/phpdb` and a driver package (for example `php-db/phpdb-mysql`) installed in the application. Neither is a runtime requirement of `webware-log`; `php-db/phpdb-mysql` appears only in its `require-dev` and `suggest`.
- `PhpDb\Adapter\AdapterInterface` registered in the PSR-11 container. `php-db/phpdb`'s own `ConfigProvider` wires it; refer to the [php-db documentation](https://docs.php-db.dev/). phpdb does not share `laminas-db`'s configuration structure.
- The log table created. Import the fixture described in [Installation](installation.md#database-table), or run `log:init-db`.

### Wiring

`Webware\Log\Handler\PhpDbHandlerFactory` builds the handler from the adapter, the `table` config key and the identity field. It is public and stays supported, but `Webware\Log\ConfigProvider` does not register it. Register it in your own config:

```php
// config/autoload/log.local.php
use Webware\Log\Handler\PhpDbHandler;
use Webware\Log\Handler\PhpDbHandlerFactory;

return [
    'dependencies' => [
        'factories' => [
            PhpDbHandler::class => PhpDbHandlerFactory::class,
        ],
    ],
];
```

That is the whole wiring. `LogFactory` checks for `PhpDbHandler` in the container and pushes it onto the logger when it is present, so registering the factory is what switches database logging on. With the key absent the check fails and no database handler is attached, which is why an application that never installs `php-db/phpdb` is unaffected by the handler's absence.

Push only one database handler: two writing the same table would store every record twice.

### Column Mapping

| Log Record Field | DB Column |
|---|---|
| `channel` | `channel` |
| `level_name` | `level` |
| `extra.uuid` | `uuid` |
| `message` | `message` |
| `datetime` (unix) | `time` |
| `extra.{identity field}` | `user_identifier` |
| `context` and filtered `extra` (JSON) | `context` |

## Identity Field

The handler reads the authenticated user from the record's `extra` under the key resolved from `authentication.username`, defaulting to `email`. That is the same key `MonologMiddleware` writes, so the two stay in step when the column is renamed. See [Identity Field](configuration.md#identity-field).
