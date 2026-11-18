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

use Laminas\Stratigility\Middleware\ErrorHandler;
use Monolog\Logger;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use Webware\Log\ConfigProvider;
use Webware\Log\Listener\MezzioErrorListener;
use Webware\Log\LogChannel;

/**
 * @import-type LogDefaults from ConfigProvider
 */
final class MezzioErrorHandlerDelegator
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container, string $name, callable $callback): ErrorHandler
    {
        /** @var array{LoggerInterface::class?: LogDefaults} */
        $rawConfig = $container->get('config');

        $config = $rawConfig[LoggerInterface::class] ?? new ConfigProvider()->getConfigDefaults();

        /** @var ErrorHandler $handler */
        $handler = $callback();
        if (! $config['log_errors']) {
            return $handler;
        }

        /** @var Logger $logger */
        $logger   = $container->get(LoggerInterface::class);
        $listener = new MezzioErrorListener(
            $logger->withName(LogChannel::Error->value),
        );
        $handler->attachListener($listener);

        return $handler;
    }
}
