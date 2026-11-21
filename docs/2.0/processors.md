# Processors

Monolog processors enrich log records with additional data before they are written. This package ships two optional processors, both guarded by config flags. `LogFactory` also always pushes Monolog's `PsrLogMessageProcessor`, so PSR-3 `{placeholder}` references in a message are interpolated from the record's context whether or not either flag is set.

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

When enabled, `LogFactory` pushes `LaminasI18nProcessor` when a `Laminas\Translator\TranslatorInterface` is registered in the container. With the flag set and no translator registered, the processor is not resolved at all, so no exception is raised and messages are logged untranslated. `LaminasI18nProcessorFactory` does throw a `ServiceNotFoundException` if something else asks it to build the processor without a translator.

### Behaviour

The processor calls `$translator->translate($record->message)` and returns a new `LogRecord` with the translated message. Context and extra data are preserved unchanged.
