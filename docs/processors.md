# Processors

Monolog processors enrich log records with additional data before they are written. This package ships two optional processors, both guarded by config flags.

## RamseyUuidProcessor

Adds a [UUID v7](https://www.ietf.org/archive/id/draft-peabody-dispatch-new-uuid-format-04.txt) string to `extra.uuid` on every log record. The UUID is generated from the record's `datetime`, making it time-ordered.

### Enabling

```php
use Psr\Log\LoggerInterface;

return [
    LoggerInterface::class => [
        'process_uuid' => true,
    ],
];
```

When enabled, `LogFactory` instantiates `RamseyUuidProcessor` and pushes it onto the logger. The `uuid` column in the database table will be populated automatically.

## LaminasI18nProcessor

Translates log messages using a `Laminas\Translator\TranslatorInterface` instance from the container.

### Requirements

- `laminas/laminas-i18n` must be installed.
- `Laminas\Translator\TranslatorInterface` must be registered in the container.

### Enabling

```php
return [
    LoggerInterface::class => [
        'process_translation' => true,
    ],
];
```

When enabled, `LogFactory` retrieves `LaminasI18nProcessor` from the container, which is constructed with the translator from the container. If no translator is registered, `LaminasI18nProcessorFactory` throws a `ServiceNotFoundException`.

### Behaviour

The processor calls `$translator->translate($record->message)` and returns a new `LogRecord` with the translated message. Context and extra data are preserved unchanged.
