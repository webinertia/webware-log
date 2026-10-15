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

namespace WebwareTest\Log\Handler;

use PhpDb\Adapter\AdapterInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use Webware\Log\Handler\PhpDbHandler;
use Webware\Log\Handler\PhpDbHandlerFactory;

#[CoversClass(PhpDbHandlerFactory::class)]
#[CoversMethod(PhpDbHandlerFactory::class, '__invoke')]
final class PhpDbHandlerFactoryTest extends TestCase
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function invokeFallsBackToDefaultTableWhenNoConfig(): void
    {
        $container = $this->makeContainer([]);
        $factory   = new PhpDbHandlerFactory();
        $result    = $factory($container);

        $this->assertInstanceOf(PhpDbHandler::class, $result);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function invokeReturnsPhpDbHandler(): void
    {
        $container = $this->makeContainer([]);
        $factory   = new PhpDbHandlerFactory();
        $result    = $factory($container);

        $this->assertInstanceOf(PhpDbHandler::class, $result);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function invokeUsesTableFromConfig(): void
    {
        $container = $this->makeContainer([
            LoggerInterface::class => [
                'table'               => 'audit_log',
                'channel'             => 'app',
                'log_errors'          => false,
                'process_uuid'        => false,
                'process_translation' => false,
            ],
        ]);

        $factory = new PhpDbHandlerFactory();
        $result  = $factory($container);

        $this->assertInstanceOf(PhpDbHandler::class, $result);
    }

    /**
     * @param array<string, mixed> $config
     */
    /**
     * @throws \PHPUnit\Exception
     */
    private function makeContainer(array $config): ContainerInterface
    {
        $adapter   = $this->createStub(AdapterInterface::class);
        $container = $this->createStub(ContainerInterface::class);
        $container
            ->method('get')
            ->willReturnCallback(
                static function (string $id) use ($config, $adapter): mixed {
                    if ('config' === $id) {
                        return $config;
                    }

                    if (AdapterInterface::class === $id) {
                        return $adapter;
                    }

                    return null;
                },
            );

        return $container;
    }
}
