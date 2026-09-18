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

namespace WebwareTest\Log\Http\Middleware;

use DateTimeImmutable;
use Mezzio\Authentication\UserInterface;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Webware\Log\Http\Middleware\MonologMiddleware;

#[CoversClass(MonologMiddleware::class)]
#[CoversMethod(MonologMiddleware::class, 'process')]
final class MonologMiddlewareTest extends TestCase
{
    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function processAttachesLoggerToRequest(): void
    {
        $logger   = $this->createStub(Logger::class);
        $request  = $this->createMock(ServerRequestInterface::class);
        $handler  = $this->createStub(RequestHandlerInterface::class);
        $response = $this->createStub(ResponseInterface::class);

        $request->method('getAttribute')->willReturn(null);
        $request->expects($this->once())
            ->method('withAttribute')
            ->with(LoggerInterface::class, $logger)
            ->willReturn($request);

        $handler->method('handle')->willReturn($response);

        $result = new MonologMiddleware($logger)->process($request, $handler);

        $this->assertSame($response, $result);
    }

    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function processDoesNotPushProcessorWhenNoUserAttribute(): void
    {
        /** @var Logger&MockObject $logger */
        $logger   = $this->createMock(Logger::class);
        $request  = $this->createStub(ServerRequestInterface::class);
        $handler  = $this->createStub(RequestHandlerInterface::class);
        $response = $this->createStub(ResponseInterface::class);

        $request->method('getAttribute')->willReturn(null);
        $request->method('withAttribute')->willReturn($request);
        $handler->method('handle')->willReturn($response);

        $logger->expects($this->never())->method('pushProcessor');

        new MonologMiddleware($logger)->process($request, $handler);
    }

    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function processorAddsEmailToRecordExtra(): void
    {
        /** @var Logger&MockObject $logger */
        $logger   = $this->createMock(Logger::class);
        $request  = $this->createStub(ServerRequestInterface::class);
        $handler  = $this->createStub(RequestHandlerInterface::class);
        $response = $this->createStub(ResponseInterface::class);
        $user     = $this->createStub(UserInterface::class);

        $user->method('getIdentity')->willReturn('user@example.com');
        $request->method('getAttribute')->willReturn($user);
        $request->method('withAttribute')->willReturn($request);
        $handler->method('handle')->willReturn($response);

        $processor = null;
        $logger->expects($this->once())
            ->method('pushProcessor')
            ->willReturnCallback(static function (callable $callback) use (&$processor, $logger): Logger {
                $processor = $callback;

                return $logger;
            });

        new MonologMiddleware($logger)->process($request, $handler);

        self::assertIsCallable($processor);

        /** @var callable(LogRecord): LogRecord $processor */
        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel : 'test',
            level   : Level::Debug,
            message : 'test',
            extra   : ['foo' => 'bar'],
        );

        $result = $processor($record);

        $this->assertSame('user@example.com', $result->extra['email']);
        $this->assertSame('bar', $result->extra['foo']);
    }

    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function processPushesProcessorWhenUserAttributePresent(): void
    {
        /** @var Logger&MockObject $logger */
        $logger   = $this->createMock(Logger::class);
        $request  = $this->createStub(ServerRequestInterface::class);
        $handler  = $this->createStub(RequestHandlerInterface::class);
        $response = $this->createStub(ResponseInterface::class);
        $user     = $this->createStub(UserInterface::class);

        $user->method('getIdentity')->willReturn('user@example.com');
        $request->method('getAttribute')->willReturn($user);
        $request->method('withAttribute')->willReturn($request);
        $handler->method('handle')->willReturn($response);

        $logger->expects($this->once())->method('pushProcessor');

        new MonologMiddleware($logger)->process($request, $handler);
    }

    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function processUsesCustomAuthAttributeKey(): void
    {
        $logger   = $this->createStub(Logger::class);
        $request  = $this->createMock(ServerRequestInterface::class);
        $handler  = $this->createStub(RequestHandlerInterface::class);
        $response = $this->createStub(ResponseInterface::class);

        $request->expects($this->once())
            ->method('getAttribute')
            ->with('my_auth_user')
            ->willReturn(null);

        $request->method('withAttribute')->willReturn($request);
        $handler->method('handle')->willReturn($response);

        new MonologMiddleware($logger, 'my_auth_user')->process($request, $handler);
    }
}
