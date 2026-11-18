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

namespace Webware\Log\Container;

use Laminas\Translator\TranslatorInterface;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use Webware\Log\ConfigProvider;
use Webware\Log\Handler;
use Webware\Log\LogChannel;
use Webware\Log\Processor;

/**
 * @phpstan-import-type LogDefaults from ConfigProvider
 */
final class LogFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container): LoggerInterface
    {
        /** @var array{LoggerInterface::class?: LogDefaults} */
        $rawConfig = $container->get('config');

        $config = ! empty($rawConfig[LoggerInterface::class])
            ? $rawConfig[LoggerInterface::class]
            : new ConfigProvider()->getConfigDefaults();

        $channel = LogChannel::tryFrom($config['channel']) ?? LogChannel::App;
        $logger  = new Logger($channel->value);

        if ($container->has(Handler\PhpDbHandler::class)) {
            $phpDbHandler = $container->get(Handler\PhpDbHandler::class);
            $logger->pushHandler($phpDbHandler);
        }

        if ($config['process_uuid'] ?? false) {
            $processor = new Processor\RamseyUuidProcessor();
            $logger->pushProcessor($processor);
        }

        $processor = new PsrLogMessageProcessor(null, false);
        $logger->pushProcessor($processor);

        if (($config['process_translation'] ?? false) && $container->has(TranslatorInterface::class)) {
            $i18nProcessor = $container->get(Processor\LaminasI18nProcessor::class);
            $logger->pushProcessor($i18nProcessor);
        }

        return $logger;
    }
}
