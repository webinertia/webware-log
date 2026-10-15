# Middleware

`Webware\Log\Http\Middleware\MonologMiddleware` is a PSR-15 middleware that:

1. Reads the authenticated user from the request (if present) and pushes a processor that adds the user's identity string to every log record's `extra.email` field.
2. Attaches the logger to the request under `Psr\Log\LoggerInterface::class` so downstream middleware and handlers can retrieve it without depending on the container.

## Registering in the Pipeline

Add `MonologMiddleware` early in your pipeline so that all downstream middleware have access to the enriched logger.

**`config/pipeline.php`** (explicit pipeline):

```php
use Webware\Log\Http\Middleware\MonologMiddleware;

$app->pipe(MonologMiddleware::class);
```

**Config-driven pipeline**: uncomment the `middleware_pipeline` section in `ConfigProvider`:

```php
// src/ConfigProvider.php  (or your own config file)
'middleware_pipeline' => $this->getPipelineConfig(),
```

## Retrieving the Logger Downstream

```php
use Psr\Log\LoggerInterface;
use Psr\Http\Message\ServerRequestInterface;

// inside any handler or middleware that runs after MonologMiddleware
$logger = $request->getAttribute(LoggerInterface::class);
$logger?->info('Request handled', ['route' => $routeName]);
```

## User Identity Enrichment

The middleware looks for an authenticated user on the request under two class-name attribute keys, most preferred first:

1. `Webware\Core\UserInterface`
2. `Mezzio\Authentication\UserInterface`

Both are named directly rather than configured, because a host maps its own implementation onto one of those two contracts and attaches it under that contract's name. Webware's contract wins when a request carries both.

When one is found, a Monolog processor is pushed that adds:

```php
$record->extra[$identityField] = $userInterface->getIdentity();
```

`$identityField` is resolved from `authentication.username` in your application config, defaulting to `email`. That is the same key a host points at its identity column, so the record's `extra` key and the `user_identifier` column `PhpDbHandler` writes stay in step when the column is renamed.

This happens before the request is forwarded to the next middleware, so all log records written during that request carry the user identity automatically.

If no authenticated user is present the processor is not pushed and the `extra` key will be absent.
