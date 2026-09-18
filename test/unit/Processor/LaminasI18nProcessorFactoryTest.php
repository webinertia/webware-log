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

namespace WebwareTest\Log\Processor;

use DateTimeImmutable;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\Translator\TranslatorInterface;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Webware\Log\Processor\LaminasI18nProcessor;
use Webware\Log\Processor\LaminasI18nProcessorFactory;

#[CoversClass(LaminasI18nProcessorFactory::class)]
#[CoversMethod(LaminasI18nProcessorFactory::class, '__invoke')]
final class LaminasI18nProcessorFactoryTest extends TestCase
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function invokeReturnsLaminasI18nProcessorWhenTranslatorPresent(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects($this->once())
            ->method('translate')
            ->with('Hello world')
            ->willReturn('Hola mundo');

        $container = $this->createStub(ContainerInterface::class);

        $container->method('has')
            ->willReturnCallback(
                static fn(string $id): bool => TranslatorInterface::class === $id,
            );

        $container->method('get')
            ->willReturnCallback(
                static fn(string $id): mixed => TranslatorInterface::class === $id ? $translator : null,
            );

        $factory = new LaminasI18nProcessorFactory();
        $result  = $factory($container);

        $this->assertInstanceOf(LaminasI18nProcessor::class, $result);

        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel : 'test',
            level   : Level::Info,
            message : 'Hello world',
        );

        $this->assertSame('Hola mundo', $result($record)->message);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function invokeThrowsWhenTranslatorNotInContainer(): void
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')
            ->willReturnCallback(
                static fn(string $id): bool => false,
            );

        $factory = new LaminasI18nProcessorFactory();

        $this->expectException(ServiceNotFoundException::class);
        $this->expectExceptionMessageIs(TranslatorInterface::class . ' was not found in the container');

        $factory($container);
    }
}
