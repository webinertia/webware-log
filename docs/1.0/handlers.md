# Handlers

`webware-log` ships one Monolog handler for this release line. `Webware\Log\Handler\PhpDbHandler` writes
records to a relational database through `php-db/phpdb`.

## PhpDbHandler

`Webware\Log\ConfigProvider` registers `PhpDbHandler` in the container, and `LogFactory` pushes it onto
the logger. Nothing else has to be wired beyond the driver, the adapter and the table.

### Requirements

- `php-db/phpdb` and a driver package (for example `php-db/phpdb-mysql`) installed in the application. Neither is a runtime requirement of `webware-log`; `php-db/phpdb-mysql` appears only in its `require-dev` and `suggest`.
- `PhpDb\Adapter\AdapterInterface` registered in the PSR-11 container. `phpdb` does not share `laminas-db`'s configuration structure, so refer to the [php-db documentation](https://docs.php-db.dev/) for the adapter wiring.
- The log table created. Import the fixture described in [Installation](installation.md#database-table).

`PhpDbHandlerFactory` resolves the adapter, the `table` config key and the identity field from the
container, so the handler follows those values. Because the factory is registered, the adapter has to be
available when the logger is built; without it the container raises an exception naming
`PhpDb\Adapter\AdapterInterface`.

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

Push only one database handler: two writing the same table would store every record twice.

## Identity Field

The handler reads the authenticated user from the record's `extra` under the key named by
`authentication.username`, defaulting to `email`. See [Authentication Attribute](configuration.md#authentication-attribute)
for that config key.

`MonologMiddleware` in this release line always writes `extra.email`, whatever `authentication.username`
says. A host that changes `authentication.username` also needs a middleware that writes the identity
under the same key, or the `user_identifier` column stays empty.
