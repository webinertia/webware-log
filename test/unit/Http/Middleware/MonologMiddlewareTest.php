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
use Webware\Core\UserInterface as WebwareUserInterface;
use Webware\Log\Http\Middleware\MonologMiddleware;

#[CoversClass(MonologMiddleware::class)]
#[CoversMethod(MonologMiddleware::class, 'process')]
final class MonologMiddlewareTest extends TestCase
{
    /** The attribute key webware's own user contract is attached under. */
    private const string WEBWARE_ATTRIBUTE = WebwareUserInterface::class;

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
        $request
            ->expects($this->once())
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
    public function processIgnoresAttributesThatAreNotOneOfTheTwoContracts(): void
    {
        // The attribute key has to be one of the two contracts. An implementation
        // attached under any other key is not an identity source.
        $extra = $this->capturedExtra([
            'my_app_user' => $this->userWithIdentity('user@example.com'),
        ]);

        self::assertNull($extra);
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
        $logger
            ->expects($this->once())
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
    public function processorWritesUnderTheConfiguredIdentityField(): void
    {
        $extra = $this->capturedExtra(
            [UserInterface::class => $this->userWithIdentity('user@example.com')],
            'user_name',
        );

        self::assertSame(['user_name' => 'user@example.com'], $extra);
    }

    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function processPrefersWebwareContractOverMezzio(): void
    {
        $extra = $this->capturedExtra([
            self::WEBWARE_ATTRIBUTE => $this->userWithIdentity('webware@example.com'),
            UserInterface::class    => $this->userWithIdentity('mezzio@example.com'),
        ]);

        self::assertSame(['email' => 'webware@example.com'], $extra);
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
    public function processUsesMezzioContractWhenWebwareIsAbsent(): void
    {
        $extra = $this->capturedExtra([
            UserInterface::class => $this->userWithIdentity('mezzio@example.com'),
        ]);

        self::assertSame(['email' => 'mezzio@example.com'], $extra);
    }

    /**
     * Runs the middleware against a request carrying $attributes, then returns
     * the `extra` array the pushed processor produced for a fresh record, or
     * null when no processor was pushed.
     *
     * @param array<string, object> $attributes
     *
     * @return array<string, mixed>|null
     *
     * @throws \PHPUnit\Exception
     */
    private function capturedExtra(array $attributes, string $identityField = 'email'): ?array
    {
        $logger  = $this->createStub(Logger::class);
        $request = $this->createStub(ServerRequestInterface::class);
        $handler = $this->createStub(RequestHandlerInterface::class);

        $request
            ->method('getAttribute')
            ->willReturnCallback(
                static fn(string $attribute): ?object => $attributes[$attribute] ?? null,
            );
        $request->method('withAttribute')->willReturn($request);
        $handler->method('handle')->willReturn($this->createStub(ResponseInterface::class));

        $processor = null;
        $logger
            ->method('pushProcessor')
            ->willReturnCallback(
                static function (callable $callback) use (&$processor, $logger): Logger {
                    $processor = $callback;

                    return $logger;
                },
            );

        new MonologMiddleware($logger, $identityField)->process($request, $handler);

        if (null === $processor) {
            return null;
        }

        /** @var callable(LogRecord): LogRecord $processor */
        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel : 'test',
            level   : Level::Debug,
            message : 'test',
        );

        return $processor($record)->extra;
    }

    private function userWithIdentity(string $identity): UserInterface
    {
        $user = $this->createStub(UserInterface::class);
        $user->method('getIdentity')->willReturn($identity);

        return $user;
    }
}
