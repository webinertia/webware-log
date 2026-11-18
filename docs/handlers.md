# Handlers

`webware-log` registers no Monolog handler. `LogFactory` returns a `Monolog\Logger` with no handlers attached, so records are discarded until the application pushes a sink of its own. This is deliberate: pushing a relational database handler on every consumer's behalf would make a database a requirement of a package that may be used without one.

`PhpDbHandler` is the handler this package ships for writing records to a database table. Wiring it is the application's decision.

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
use Psr\Log\LoggerInterface;
use Webware\Log\Handler\PhpDbHandler;
use Webware\Log\Handler\PhpDbHandlerFactory;

return [
    'dependencies' => [
        'factories' => [
            PhpDbHandler::class    => PhpDbHandlerFactory::class,
            LoggerInterface::class => MyApp\Container\DatabaseLogFactory::class,
        ],
    ],
];
```

Registering the factory makes the handler resolvable; it does not put records into the table. The handler also has to be pushed onto the logger, which is what replacing the `LoggerInterface` factory does:

```php
namespace MyApp\Container;

use Monolog\Logger;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use Webware\Log\Container\LogFactory;
use Webware\Log\Handler\PhpDbHandler;

final class DatabaseLogFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container): LoggerInterface
    {
        /** @var Logger $logger */
        $logger = (new LogFactory())($container);
        $logger->pushHandler($container->get(PhpDbHandler::class));

        return $logger;
    }
}
```

`LogFactory` builds the logger, channel and processors as usual; the decorator adds the sink. Push only one database handler: two writing the same table would store every record twice.

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
