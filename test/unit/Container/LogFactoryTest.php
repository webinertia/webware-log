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

use Laminas\Translator\TranslatorInterface;
use Monolog\Handler\HandlerInterface;
use Monolog\Logger;
use Monolog\Processor\ProcessorInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use Webware\Log\Container\LogFactory;
use Webware\Log\Handler\PhpDbHandler;
use Webware\Log\Processor\LaminasI18nProcessor;

#[CoversClass(LogFactory::class)]
#[CoversMethod(LogFactory::class, '__invoke')]
final class LogFactoryTest extends TestCase
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function invokeFallsBackToDefaultsWhenNoConfig(): void
    {
        $handler   = $this->createStub(HandlerInterface::class);
        $container = $this->makeContainer(
            [],
            [PhpDbHandler::class => $handler],
        );

        $factory = new LogFactory();

        /** @var Logger $logger */
        $logger = $factory($container);

        $this->assertSame('app', $logger->getName());
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function invokePushesTranslationProcessorWhenConfigured(): void
    {
        $handler   = $this->createStub(HandlerInterface::class);
        $processor = $this->createStub(ProcessorInterface::class);

        $container = $this->makeContainer(
            [
                LoggerInterface::class => [
                    'channel'             => 'app',
                    'log_errors'          => false,
                    'process_uuid'        => false,
                    'process_translation' => true,
                    'table'               => 'log',
                ],
            ],
            [
                PhpDbHandler::class         => $handler,
                TranslatorInterface::class  => $this->createStub(TranslatorInterface::class),
                LaminasI18nProcessor::class => $processor,
            ],
        );

        $factory = new LogFactory();

        /** @var Logger $logger */
        $logger = $factory($container);

        $this->assertInstanceOf(LoggerInterface::class, $logger);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function invokeReturnsLoggerInterface(): void
    {
        $handler   = $this->createStub(HandlerInterface::class);
        $container = $this->makeContainer(
            [],
            [PhpDbHandler::class => $handler],
        );

        $factory = new LogFactory();
        $logger  = $factory($container);

        $this->assertInstanceOf(LoggerInterface::class, $logger);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function invokeUsesConfiguredChannel(): void
    {
        $handler   = $this->createStub(HandlerInterface::class);
        $container = $this->makeContainer(
            [
                LoggerInterface::class => [
                    'channel'             => 'security',
                    'log_errors'          => false,
                    'process_uuid'        => false,
                    'process_translation' => false,
                    'table'               => 'log',
                ],
            ],
            [PhpDbHandler::class => $handler],
        );

        $factory = new LogFactory();

        /** @var Logger $logger */
        $logger = $factory($container);

        $this->assertSame('security', $logger->getName());
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

        $container
            ->method('has')
            ->willReturnCallback(
                static fn(string $id): bool => isset($services[$id]),
            );

        return $container;
    }
}
