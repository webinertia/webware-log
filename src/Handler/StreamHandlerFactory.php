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

namespace Webware\Log\Handler;

use InvalidArgumentException;
use Monolog\Handler\StreamHandler;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use Webware\Log\ConfigProvider;
use Webware\Log\LogChannel;

use function str_replace;

/**
 * @import-type LogDefaults from ConfigProvider
 */
final class StreamHandlerFactory
{
    /** Placeholder in the `logFile` config value replaced with the configured channel. */
    public const string CHANNEL_PLACEHOLDER = '{channel}';

    /**
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException If the configured `logFile` is not a usable path.
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container): StreamHandler
    {
        /** @var array{'Psr\Log\LoggerInterface'?: LogDefaults} $rawConfig */
        $rawConfig = $container->get('config');

        $config = [
            ...new ConfigProvider()->getConfigDefaults(),
            ...($rawConfig[LoggerInterface::class] ?? []),
        ];

        $channel = LogChannel::tryFrom($config['channel']) ?? LogChannel::App;

        // StreamHandler creates the directory and the file on the first write, so the
        // path is returned as configured rather than pre-created here.
        return new StreamHandler(
            str_replace(self::CHANNEL_PLACEHOLDER, $channel->value, $config['logFile']),
        );
    }
}
