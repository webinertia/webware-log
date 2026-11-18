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

use Monolog\Logger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use Webware\Log\Handler\StreamHandlerFactory;

use function file_get_contents;
use function getcwd;
use function is_dir;
use function restore_error_handler;
use function rmdir;
use function set_error_handler;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

#[CoversClass(StreamHandlerFactory::class)]
#[CoversMethod(StreamHandlerFactory::class, '__invoke')]
final class StreamHandlerFactoryTest extends TestCase
{
    private string $directory;

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function invokeDefaultsToTheDataDirectoryAndTheConfiguredChannel(): void
    {
        $handler = new StreamHandlerFactory()($this->makeContainer([]));

        $this->assertSame(
            getcwd() . '/data/log/app.log',
            $handler->getUrl(),
        );
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function invokeReplacesTheChannelPlaceholderWithTheConfiguredChannel(): void
    {
        $handler = new StreamHandlerFactory()($this->makeContainer([
            LoggerInterface::class => [
                'channel'             => 'security',
                'log_errors'          => false,
                'logFile'             => 'data/log/{channel}.log',
                'process_uuid'        => false,
                'process_translation' => false,
                'table'               => 'log',
            ],
        ]));

        $this->assertSame(getcwd() . '/data/log/security.log', $handler->getUrl());
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function invokeUsesTheConfiguredLogFile(): void
    {
        $handler = new StreamHandlerFactory()($this->makeContainer([
            LoggerInterface::class => [
                'channel'             => 'app',
                'log_errors'          => false,
                'logFile'             => 'var/log/webware.log',
                'process_uuid'        => false,
                'process_translation' => false,
                'table'               => 'log',
            ],
        ]));

        $this->assertSame(getcwd() . '/var/log/webware.log', $handler->getUrl());
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function invokeWritesToAFileItCreatesWithTheDirectory(): void
    {
        $path = "{$this->directory}/log/app.log";

        $handler = new StreamHandlerFactory()($this->makeContainer([
            LoggerInterface::class => [
                'channel'             => 'app',
                'log_errors'          => false,
                'logFile'             => $path,
                'process_uuid'        => false,
                'process_translation' => false,
                'table'               => 'log',
            ],
        ]));

        $this->assertFalse(is_dir("{$this->directory}/log"));

        $logger = new Logger('app', [$handler]);
        $logger->info('created on first write');

        $this->assertFileExists($path);
        $this->assertStringContainsString('created on first write', (string) file_get_contents($path));
    }

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/webware-log-' . uniqid('', more_entropy: true);
    }

    protected function tearDown(): void
    {
        // StreamHandler closes the file it opened when the handler is destroyed, so the
        // directory is removed with the native functions it created it with.
        set_error_handler(static fn(): bool => true);

        try {
            $logDirectory = "{$this->directory}/log";

            if (is_dir($logDirectory)) {
                unlink("{$logDirectory}/app.log");
                rmdir($logDirectory);
            }

            if (is_dir($this->directory)) {
                rmdir($this->directory);
            }
        } finally {
            restore_error_handler();
        }
    }

    /**
     * @param array<string, mixed> $config
     * @throws \PHPUnit\Exception
     */
    private function makeContainer(array $config): ContainerInterface
    {
        $container = $this->createStub(ContainerInterface::class);

        $container
            ->method('get')
            ->willReturnCallback(
                static function (string $id) use ($config): mixed {
                    if ('config' === $id) {
                        return $config;
                    }

                    return null;
                },
            );

        return $container;
    }
}
