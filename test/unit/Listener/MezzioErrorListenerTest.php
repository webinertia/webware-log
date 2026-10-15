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

namespace WebwareTest\Log\Listener;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Webware\Log\Listener\MezzioErrorListener;

#[CoversClass(MezzioErrorListener::class)]
#[CoversMethod(MezzioErrorListener::class, '__invoke')]
final class MezzioErrorListenerTest extends TestCase
{
    private LoggerInterface&MockObject $logger;

    private MezzioErrorListener $listener;

    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function invokeIncludesExceptionInContext(): void
    {
        $exception = new RuntimeException('error');
        $request   = $this->createStub(ServerRequestInterface::class);
        $response  = $this->createStub(ResponseInterface::class);

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'error',
                $this->callback(static fn(array $context) => $context['exception'] === $exception),
            );

        ($this->listener)($exception, $request, $response);
    }

    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function invokeIncludesRequestAndResponseInContext(): void
    {
        $exception = new RuntimeException('oops');
        $request   = $this->createStub(ServerRequestInterface::class);
        $response  = $this->createStub(ResponseInterface::class);

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'oops',
                $this->callback(
                    static fn(array $context) => $context['request'] === $request && $context['response'] === $response,
                ),
            );

        ($this->listener)($exception, $request, $response);
    }

    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function invokeLogsExceptionMessageAtErrorLevel(): void
    {
        $exception = new RuntimeException('something went wrong');
        $request   = $this->createStub(ServerRequestInterface::class);
        $response  = $this->createStub(ResponseInterface::class);

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('something went wrong', $this->arrayHasKey('exception'));

        ($this->listener)($exception, $request, $response);
    }

    /**
     * @throws \PHPUnit\Exception
     */
    #[Override]
    protected function setUp(): void
    {
        $this->logger   = $this->createMock(LoggerInterface::class);
        $this->listener = new MezzioErrorListener($this->logger);
    }
}
