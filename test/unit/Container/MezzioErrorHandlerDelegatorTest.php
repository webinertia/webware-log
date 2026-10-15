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

namespace WebwareTest\Log\Container;

use Laminas\Stratigility\Middleware\ErrorHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use Webware\Log\Container\MezzioErrorHandlerDelegator;
use Webware\Log\Listener\MezzioErrorListener;

#[CoversClass(MezzioErrorHandlerDelegator::class)]
#[CoversMethod(MezzioErrorHandlerDelegator::class, '__invoke')]
final class MezzioErrorHandlerDelegatorTest extends TestCase
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function invokeAttachesListenerWhenLogErrorsTrue(): void
    {
        $errorHandler = $this->makeHandler();

        $logger = $this->createStub(Logger::class);
        $logger->method('withName')->willReturn($logger);

        $container = $this->makeContainer(
            [
                LoggerInterface::class => [
                    'log_errors'          => true,
                    'channel'             => 'app',
                    'process_uuid'        => false,
                    'process_translation' => false,
                    'table'               => 'log',
                ],
            ],
            [LoggerInterface::class => $logger],
        );

        $errorHandler
            ->expects($this->once())
            ->method('attachListener')
            ->with($this->isInstanceOf(MezzioErrorListener::class));

        $delegator = new MezzioErrorHandlerDelegator();
        $result    = $delegator($container, ErrorHandler::class, static fn() => $errorHandler);

        $this->assertSame($errorHandler, $result);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function invokeFallsBackToDefaultsWhenNoLoggerConfig(): void
    {
        $errorHandler = $this->makeHandler();
        $container    = $this->makeContainer([]);

        $errorHandler->expects($this->never())->method('attachListener');

        $delegator = new MezzioErrorHandlerDelegator();
        $result    = $delegator($container, ErrorHandler::class, static fn() => $errorHandler);

        $this->assertSame($errorHandler, $result);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function invokeReturnsErrorHandlerUnchangedWhenLogErrorsFalse(): void
    {
        $errorHandler = $this->makeHandler();
        $container    = $this->makeContainer([
            LoggerInterface::class => [
                'log_errors'          => false,
                'channel'             => 'app',
                'process_uuid'        => false,
                'process_translation' => false,
                'table'               => 'log',
            ],
        ]);

        $errorHandler->expects($this->never())->method('attachListener');

        $delegator = new MezzioErrorHandlerDelegator();
        $result    = $delegator($container, ErrorHandler::class, static fn() => $errorHandler);

        $this->assertSame($errorHandler, $result);
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $services
     */
    /**
     * @throws \PHPUnit\Exception
     */
    private function makeContainer(array $config, array $services = []): ContainerInterface
    {
        $container = $this->createStub(ContainerInterface::class);
        $container
            ->method('get')
            ->willReturnCallback(
                static function (string $id) use ($config, $services): mixed {
                    if ('config' === $id) {
                        return $config;
                    }

                    return $services[$id] ?? null;
                },
            );

        return $container;
    }

    /**
     * @throws \PHPUnit\Exception
     */
    private function makeHandler(): ErrorHandler&MockObject
    {
        return $this->createMock(ErrorHandler::class);
    }
}
