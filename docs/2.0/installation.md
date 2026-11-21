# Installation

## Requirements

- PHP 8.4.1 or later
- A Mezzio application (PSR-15 / PSR-11 container)

## Composer

```bash
composer require webware/webware-log
```

`laminas/laminas-component-installer` will prompt you to inject `Webware\Log\ConfigProvider` automatically into your application's config aggregator. Accept the prompt or add it manually:

```php
// config/config.php
new Webware\Log\ConfigProvider(),
```

## Database Table

One SQL fixture is provided for MySQL. For any other database supported by `php-db/phpdb` use the fixture as a template.

```bash
# import from the project root
mysql -u root -p my_database < vendor/webware/webware-log/test/integration/TestFixtures/mysql.sql
```

The fixture creates a `log` table with the following columns:

| Column | Type | Notes |
|---|---|---|
| `id` | `INT UNSIGNED AUTO_INCREMENT` | Primary key |
| `uuid` | `CHAR(36)` | UUID v7 (populated when `process_uuid: true`), unique |
| `channel` | `VARCHAR(255)` | Monolog channel name, indexed |
| `level` | `VARCHAR(9)` | Monolog level name (e.g. `ERROR`) |
| `user_identifier` | `VARCHAR(320)` | Authenticated user identity (nullable) |
| `message` | `LONGTEXT` | Formatted log message |
| `time` | `INT UNSIGNED` | Unix timestamp |
| `context` | `LONGTEXT` | PSR-3 context and the extra data that is not mapped to a column, as JSON (nullable) |

The same table can be created from the console with `log:init-db`, which takes `--drop` to drop the
existing table first. That command comes from `webware/webware-console`, which this package only
suggests; the fixture is the answer when you do not run a webware console.
