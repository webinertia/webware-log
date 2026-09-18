# webware/webware-log

[![PHP Version](https://img.shields.io/packagist/php-v/webware/webware-log)](https://packagist.org/packages/webware/webware-log)
[![Latest Stable Version](https://img.shields.io/packagist/v/webware/webware-log)](https://packagist.org/packages/webware/webware-log)
[![License](https://img.shields.io/github/license/webinertia/webware-log)](LICENSE)
[![Continuous Integration](https://github.com/webinertia/webware-log/actions/workflows/continuous-integration.yml/badge.svg)](https://github.com/webinertia/webware-log/actions/workflows/continuous-integration.yml)
[![codecov](https://codecov.io/gh/webinertia/webware-log/graph/badge.svg)](https://codecov.io/gh/webinertia/webware-log)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fwebinertia%2Fwebware-log%2F1.1.x)](https://dashboard.stryker-mutator.io/reports/github.com/webinertia/webware-log/1.1.x)

This package provides logging via Monolog for Mezzio (PSR-15) applications.
It provides log handlers backed by `php-db/phpdb` for writing logs to a database table.
It also provides a PSR-14 event listener for error logging.

## Documentation

- [Installation](docs/installation.md)
- [Configuration Reference](docs/configuration.md)
- [Middleware](docs/middleware.md)
- [Handlers](docs/handlers.md)
- [Processors](docs/processors.md)
- [Event-Driven Logging (PSR-14)](docs/events.md)
- [Error Logging](docs/error-logging.md)

## Quick Start

Install the package and let `laminas-component-installer` inject the `ConfigProvider`:

```bash
composer require webware/webware-log
```

Import the database schema and add the package's `ConfigProvider` to your config aggregator if not done automatically. Then pipe the middleware into your application pipeline:

```php
// config/pipeline.php
$app->pipe(\Webware\Log\Http\Middleware\MonologMiddleware::class);
```

Enable optional features via config:

```php
// config/autoload/log.local.php
use Psr\Log\LoggerInterface;

return [
    LoggerInterface::class => [
        'log_errors'     => true,  // auto-log uncaught exceptions
        'process_uuid'   => true,  // add UUID v7 to every record
        'channel'        => 'app', // LogChannel enum value
    ],
];
```

See the [full documentation](docs/) for details on all options, handlers, processors, and event-driven logging.
