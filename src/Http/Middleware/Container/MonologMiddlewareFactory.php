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

namespace Webware\Log\Http\Middleware\Container;

use Monolog\Logger;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use Webware\Log\Container\IdentityField;
use Webware\Log\Http\Middleware\MonologMiddleware;

class MonologMiddlewareFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container): MonologMiddleware
    {
        /** @var Logger */
        $logger = $container->get(LoggerInterface::class);

        /** @var array<string, mixed> $config */
        $config = $container->get('config');

        return new MonologMiddleware($logger, IdentityField::resolve($config));
    }
}
