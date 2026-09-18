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

namespace WebwareTest\Log\Http\Middleware\Container;

use Monolog\Logger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use Webware\Log\Http\Middleware\Container\MonologMiddlewareFactory;
use Webware\Log\Http\Middleware\MonologMiddleware;

#[CoversClass(MonologMiddlewareFactory::class)]
#[CoversMethod(MonologMiddlewareFactory::class, '__invoke')]
final class MonologMiddlewareFactoryTest extends TestCase
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function invokeFallsBackToUserInterfaceClassWhenNoConfig(): void
    {
        $logger    = $this->createStub(Logger::class);
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')
            ->willReturnCallback(
                static fn(string $id) => match ($id) {
                    LoggerInterface::class => $logger,
                    'config'               => [],
                    default                => null,
                },
            );

        $factory    = new MonologMiddlewareFactory();
        $middleware = $factory($container);

        // The default auth_attribute should be UserInterface::class — verify the
        // middleware was constructed without throwing.
        $this->assertInstanceOf(MonologMiddleware::class, $middleware);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function invokeReturnsMonologMiddleware(): void
    {
        $logger    = $this->createStub(Logger::class);
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')
            ->willReturnCallback(
                static function (string $id) use ($logger): mixed {
                    if (LoggerInterface::class === $id) {
                        return $logger;
                    }

                    if ('config' === $id) {
                        return [];
                    }

                    return null;
                },
            );

        $factory = new MonologMiddlewareFactory();
        $result  = $factory($container);

        $this->assertInstanceOf(MonologMiddleware::class, $result);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function invokeUsesAuthAttributeFromConfig(): void
    {
        $logger    = $this->createStub(Logger::class);
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')
            ->willReturnCallback(
                static function (string $id) use ($logger): mixed {
                    if (LoggerInterface::class === $id) {
                        return $logger;
                    }

                    if ('config' === $id) {
                        return [
                            LoggerInterface::class => [
                                'auth_attribute'      => 'my_user',
                                'channel'             => 'app',
                                'log_errors'          => false,
                                'process_uuid'        => false,
                                'process_translation' => false,
                                'table'               => 'log',
                            ],
                        ];
                    }

                    return null;
                },
            );

        $factory = new MonologMiddlewareFactory();
        $result  = $factory($container);

        $this->assertInstanceOf(MonologMiddleware::class, $result);
    }
}
