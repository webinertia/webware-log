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
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Monolog\Processor\ProcessorInterface;
use Monolog\Processor\PsrLogMessageProcessor;
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
use Webware\Log\Processor\RamseyUuidProcessor;

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
    public function invokeAlwaysPushesPsrLogMessageProcessor(): void
    {
        $container = $this->makeContainer([]);

        $factory = new LogFactory();

        /** @var Logger $logger */
        $logger = $factory($container);

        $this->assertSame(1, $this->countProcessorsOfType($logger, PsrLogMessageProcessor::class));
    }

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
    public function invokeKeepsContextFieldsUsedByPsrLogMessageProcessor(): void
    {
        $handler = new TestHandler();

        // LogFactory does not type-check the service it resolves for this key, so a TestHandler is accepted.
        $container = $this->makeContainer([], [PhpDbHandler::class => $handler]);

        $factory = new LogFactory();

        /** @var Logger $logger */
        $logger = $factory($container);

        $logger->info('hello {name}', ['name' => 'Joey']);

        $records = $handler->getRecords();

        $this->assertCount(1, $records);
        $this->assertArrayHasKey('name', $records[0]->context);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function invokePushesHandlerProvidedByContainer(): void
    {
        $handler   = $this->createStub(HandlerInterface::class);
        $container = $this->makeContainer([], [PhpDbHandler::class => $handler]);

        $factory = new LogFactory();

        /** @var Logger $logger */
        $logger = $factory($container);

        $this->assertSame([$handler], $logger->getHandlers());
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function invokePushesNoHandlerWhenContainerLacksOne(): void
    {
        $container = $this->makeContainer([]);

        $factory = new LogFactory();

        /** @var Logger $logger */
        $logger = $factory($container);

        $this->assertSame([], $logger->getHandlers());
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function invokePushesNoTranslationProcessorWhenDisabled(): void
    {
        $translator           = $this->createStub(TranslatorInterface::class);
        $translationProcessor = new LaminasI18nProcessor($translator);

        $container = $this->makeContainer(
            [
                LoggerInterface::class => [
                    'channel'             => 'app',
                    'log_errors'          => false,
                    'process_uuid'        => false,
                    'process_translation' => false,
                    'table'               => 'log',
                ],
            ],
            [
                TranslatorInterface::class  => $translator,
                LaminasI18nProcessor::class => $translationProcessor,
            ],
        );

        $factory = new LogFactory();

        /** @var Logger $logger */
        $logger = $factory($container);

        $this->assertSame(0, $this->countProcessorsOfType($logger, LaminasI18nProcessor::class));
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function invokePushesNoTranslationProcessorWhenFlagAbsent(): void
    {
        $translator           = $this->createStub(TranslatorInterface::class);
        $translationProcessor = new LaminasI18nProcessor($translator);

        $container = $this->makeContainer(
            [
                LoggerInterface::class => [
                    'channel'      => 'app',
                    'log_errors'   => false,
                    'process_uuid' => false,
                    'table'        => 'log',
                ],
            ],
            [
                TranslatorInterface::class  => $translator,
                LaminasI18nProcessor::class => $translationProcessor,
            ],
        );

        $factory = new LogFactory();

        /** @var Logger $logger */
        $logger = $factory($container);

        $this->assertSame(0, $this->countProcessorsOfType($logger, LaminasI18nProcessor::class));
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function invokePushesNoTranslationProcessorWhenTranslatorMissing(): void
    {
        $container = $this->makeContainer([
            LoggerInterface::class => [
                'channel'             => 'app',
                'log_errors'          => false,
                'process_uuid'        => false,
                'process_translation' => true,
                'table'               => 'log',
            ],
        ]);

        $factory = new LogFactory();

        /** @var Logger $logger */
        $logger = $factory($container);

        $this->assertSame(0, $this->countProcessorsOfType($logger, LaminasI18nProcessor::class));
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function invokePushesNoUuidProcessorWhenDisabled(): void
    {
        $container = $this->makeContainer([
            LoggerInterface::class => [
                'channel'             => 'app',
                'log_errors'          => false,
                'process_uuid'        => false,
                'process_translation' => false,
                'table'               => 'log',
            ],
        ]);

        $factory = new LogFactory();

        /** @var Logger $logger */
        $logger = $factory($container);

        $this->assertSame(0, $this->countProcessorsOfType($logger, RamseyUuidProcessor::class));
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function invokePushesNoUuidProcessorWhenFlagAbsent(): void
    {
        $container = $this->makeContainer([
            LoggerInterface::class => [
                'channel'             => 'app',
                'log_errors'          => false,
                'process_translation' => false,
                'table'               => 'log',
            ],
        ]);

        $factory = new LogFactory();

        /** @var Logger $logger */
        $logger = $factory($container);

        $this->assertSame(0, $this->countProcessorsOfType($logger, RamseyUuidProcessor::class));
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function invokePushesPsrLogMessageProcessorWithConfiguredProcessors(): void
    {
        $handler              = $this->createStub(HandlerInterface::class);
        $translator           = $this->createStub(TranslatorInterface::class);
        $translationProcessor = new LaminasI18nProcessor($translator);

        $container = $this->makeContainer(
            [
                LoggerInterface::class => [
                    'channel'             => 'app',
                    'log_errors'          => false,
                    'process_uuid'        => true,
                    'process_translation' => true,
                    'table'               => 'log',
                ],
            ],
            [
                PhpDbHandler::class         => $handler,
                TranslatorInterface::class  => $translator,
                LaminasI18nProcessor::class => $translationProcessor,
            ],
        );

        $factory = new LogFactory();

        /** @var Logger $logger */
        $logger = $factory($container);

        $this->assertSame(1, $this->countProcessorsOfType($logger, PsrLogMessageProcessor::class));
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
    public function invokePushesTranslationProcessorWhenEnabledAndAvailable(): void
    {
        $translator           = $this->createStub(TranslatorInterface::class);
        $translationProcessor = new LaminasI18nProcessor($translator);

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
                TranslatorInterface::class  => $translator,
                LaminasI18nProcessor::class => $translationProcessor,
            ],
        );

        $factory = new LogFactory();

        /** @var Logger $logger */
        $logger = $factory($container);

        $this->assertContains($translationProcessor, $logger->getProcessors());
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function invokePushesUuidProcessorWhenEnabled(): void
    {
        $container = $this->makeContainer([
            LoggerInterface::class => [
                'channel'             => 'app',
                'log_errors'          => false,
                'process_uuid'        => true,
                'process_translation' => false,
                'table'               => 'log',
            ],
        ]);

        $factory = new LogFactory();

        /** @var Logger $logger */
        $logger = $factory($container);

        $this->assertSame(1, $this->countProcessorsOfType($logger, RamseyUuidProcessor::class));
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
     * @param class-string $processorClass
     */
    private function countProcessorsOfType(Logger $logger, string $processorClass): int
    {
        $count = 0;

        foreach ($logger->getProcessors() as $processor) {
            if (! $processor instanceof $processorClass) {
                continue;
            }

            ++$count;
        }

        return $count;
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
