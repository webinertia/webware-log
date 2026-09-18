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

**Config-driven pipeline** — uncomment the `middleware_pipeline` section in `ConfigProvider`:

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

When a `Mezzio\Authentication\UserInterface` instance is found on the request, a Monolog processor is pushed that adds:

```php
$record->extra['email'] = $userInterface->getIdentity();
```

This happens before the request is forwarded to the next middleware, so all log records written during that request carry the user identity automatically.

If no authenticated user is present the processor is not pushed and the `extra.email` key will be absent.
