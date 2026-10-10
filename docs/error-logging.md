# Error Logging

`webware-log` can automatically log uncaught exceptions that reach Mezzio's `ErrorHandler` without replacing the framework's default error-handling behaviour.

## Enabling

```php
use Psr\Log\LoggerInterface;

return [
    LoggerInterface::class => [
        'log_errors' => true,
    ],
];
```

When `log_errors` is `true`, `MezzioErrorHandlerDelegator` is invoked during container build. It attaches `MezzioErrorListener` to the `ErrorHandler` as an error listener.

## How It Works

`MezzioErrorHandlerDelegator` wraps the existing `ErrorHandler` instance (it does **not** replace it). It calls `$errorHandler->attachListener(new MezzioErrorListener($logger))`.

`MezzioErrorListener` implements the `ErrorListenerInterface` expected by `laminas-stratigility`:

```php
public function __invoke(
    Throwable $e,
    ServerRequestInterface $request,
    ResponseInterface $response,
): void {
    $this->logger->error($e->getMessage(), [
        'exception' => $e,
        'request'   => $request,
        'response'  => $response,
    ]);
}
```

The error is logged at `ERROR` level with the exception, request, and response available in the context array for downstream handlers or formatters.

## What Is Not Changed

- The `ErrorHandler` continues to generate its own error response as normal.
- No exception is swallowed.
- The delegator pattern means the `ErrorHandler` service ID in the container still resolves to the original `ErrorHandler` instance - only a listener is attached to it.
