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

use DateTimeImmutable;
use Mezzio\Authentication\UserInterface;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
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
        $container
            ->method('get')
            ->willReturnCallback(
                static fn(string $id) => match ($id) {
                    LoggerInterface::class => $logger,
                    'config'               => [],
                    default                => null,
                },
            );

        $factory    = new MonologMiddlewareFactory();
        $middleware = $factory($container);

        // With no authentication config the middleware falls back to the default
        // identity field; verify it was constructed without throwing.
        $this->assertInstanceOf(MonologMiddleware::class, $middleware);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function invokePassesTheConfiguredIdentityFieldToTheMiddleware(): void
    {
        $logger = $this->createStub(Logger::class);

        $container = $this->containerWith([
            LoggerInterface::class => [],
            'authentication'       => ['username' => 'user_name'],
        ], $logger);

        $processor = null;
        $logger
            ->method('pushProcessor')
            ->willReturnCallback(
                static function (callable $callback) use (&$processor, $logger): Logger {
                    $processor = $callback;

                    return $logger;
                },
            );

        $middleware = new MonologMiddlewareFactory()($container);

        /** @var UserInterface $user */
        $user = $this->createStub(UserInterface::class);
        $user->method('getIdentity')->willReturn('user@example.com');

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getAttribute')->willReturn($user);
        $request->method('withAttribute')->willReturn($request);

        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($this->createStub(ResponseInterface::class));

        $middleware->process($request, $handler);

        self::assertNotNull($processor);

        /** @var callable(LogRecord): LogRecord $processor */
        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel : 'test',
            level   : Level::Debug,
            message : 'test',
        );

        self::assertSame(['user_name' => 'user@example.com'], $processor($record)->extra);
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
        $container
            ->method('get')
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
     * @param array<string, mixed> $config
     */
    private function containerWith(array $config, Logger $logger): ContainerInterface
    {
        $container = $this->createStub(ContainerInterface::class);
        $container
            ->method('get')
            ->willReturnCallback(
                static fn(string $id): mixed => match ($id) {
                    LoggerInterface::class => $logger,
                    'config'               => $config,
                    default                => null,
                },
            );

        return $container;
    }
}
