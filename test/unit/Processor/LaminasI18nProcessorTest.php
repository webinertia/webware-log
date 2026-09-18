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
use Laminas\Translator\TranslatorInterface;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Webware\Log\Processor\LaminasI18nProcessor;

#[CoversClass(LaminasI18nProcessor::class)]
#[CoversMethod(LaminasI18nProcessor::class, '__construct')]
#[CoversMethod(LaminasI18nProcessor::class, '__invoke')]
final class LaminasI18nProcessorTest extends TestCase
{
    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function invokePreservesContextAndExtraWhenTranslating(): void
    {
        /** @var Stub&TranslatorInterface $translator */
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('translate')->willReturn('translated');

        $processor = new LaminasI18nProcessor($translator);

        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel : 'test',
            level   : Level::Info,
            message : 'original',
            context : ['key' => 'value'],
            extra   : ['foo' => 'bar'],
        );

        $result = $processor($record);

        $this->assertSame(['key' => 'value'], $result->context);
        $this->assertSame(['foo' => 'bar'], $result->extra);
    }

    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function invokeReturnsLogRecord(): void
    {
        $processor = new LaminasI18nProcessor($this->createStub(TranslatorInterface::class));

        $result = $processor($this->makeRecord());

        $this->assertInstanceOf(LogRecord::class, $result);
    }

    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function invokeTranslatesMessageWithInjectedTranslator(): void
    {
        /** @var MockObject&TranslatorInterface $translator */
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects($this->once())
            ->method('translate')
            ->with('Hello world')
            ->willReturn('Hola mundo');

        $processor = new LaminasI18nProcessor($translator);

        $record = $this->makeRecord('Hello world');
        $result = $processor($record);

        $this->assertSame('Hola mundo', $result->message);
    }

    private function makeRecord(string $message = 'Hello world'): LogRecord
    {
        return new LogRecord(
            datetime: new DateTimeImmutable(),
            channel : 'test',
            level   : Level::Info,
            message : $message,
        );
    }
}
