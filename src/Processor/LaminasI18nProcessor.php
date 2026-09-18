<?php

declare(strict_types=1);

/**
 * This file is part of the Webware Log package.
 *
 * Copyright (c) 2026 Joey Smith <jsmith@webinertia.net>
 * and contributors.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webware\Log\Processor;

use Laminas\Translator\TranslatorInterface;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Override;

final class LaminasI18nProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {}

    #[Override]
    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            message: $this->translator->translate($record->message),
            context: $record->context,
            extra  : $record->extra,
        );
    }
}
