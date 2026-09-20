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

use Monolog\Logger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use Webware\Log\Listener\Container\Psr3LogPsr14ListenerFactory;
use Webware\Log\Listener\Psr3LogPsr14Listener;

#[CoversClass(Psr3LogPsr14ListenerFactory::class)]
#[CoversMethod(Psr3LogPsr14ListenerFactory::class, '__invoke')]
final class ListenerFactoriesTest extends TestCase
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function psr14FactoryReturnsPsr14Listener(): void
    {
        $logger    = $this->createStub(Logger::class);
        $container = $this->makeContainer($logger);

        $factory = new Psr3LogPsr14ListenerFactory();
        $result  = $factory($container);

        $this->assertInstanceOf(Psr3LogPsr14Listener::class, $result);
    }

    /**
     * @throws \PHPUnit\Exception
     */
    private function makeContainer(Logger $logger): ContainerInterface
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')
            ->willReturnCallback(
                static fn(string $id): mixed => LoggerInterface::class === $id ? $logger : null,
            );

        return $container;
    }
}
