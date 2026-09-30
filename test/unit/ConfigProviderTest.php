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

namespace WebwareTest\Log;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\Log\LoggerInterface;
use Webware\Console\ConsoleInterface;
use Webware\Log\ConfigProvider;
use Webware\Log\Console\Container\InitDbCommandFactory;
use Webware\Log\Console\InitDbCommand;
use Webware\Log\Event\LogEvent;
use Webware\Log\Listener\Psr3LogPsr14Listener;
use Webware\Log\LogChannel;

#[CoversClass(ConfigProvider::class)]
#[CoversMethod(ConfigProvider::class, '__invoke')]
#[CoversMethod(ConfigProvider::class, 'getConfigDefaults')]
#[CoversMethod(ConfigProvider::class, 'getListeners')]
#[CoversMethod(ConfigProvider::class, 'getDependencies')]
final class ConfigProviderTest extends TestCase
{
    private ConfigProvider $provider;

    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function getConfigDefaultsChannelDefaultsToApp(): void
    {
        $defaults = $this->provider->getConfigDefaults();

        $this->assertSame(LogChannel::App->value, $defaults['channel']);
    }

    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function getConfigDefaultsProcessFlagsDefaultToFalse(): void
    {
        $defaults = $this->provider->getConfigDefaults();

        $this->assertFalse($defaults['log_errors']);
        $this->assertFalse($defaults['process_uuid']);
        $this->assertFalse($defaults['process_translation']);
    }

    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function getConfigDefaultsReturnsExpectedKeys(): void
    {
        $defaults = $this->provider->getConfigDefaults();

        $this->assertArrayHasKey('channel', $defaults);
        $this->assertArrayHasKey('log_errors', $defaults);
        $this->assertArrayHasKey('process_uuid', $defaults);
        $this->assertArrayHasKey('process_translation', $defaults);
        $this->assertArrayHasKey('table', $defaults);
    }

    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function getConfigDefaultsTableDefaultsToLog(): void
    {
        $defaults = $this->provider->getConfigDefaults();

        $this->assertSame('log', $defaults['table']);
    }

    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function getDependenciesAliasesEventDispatcherInterface(): void
    {
        $deps = $this->provider->getDependencies();

        $this->assertArrayHasKey(EventDispatcherInterface::class, $deps['aliases']);
    }

    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function getDependenciesAliasesListenerProviderInterface(): void
    {
        $deps = $this->provider->getDependencies();

        $this->assertArrayHasKey(ListenerProviderInterface::class, $deps['aliases']);
    }

    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function getDependenciesRegistersTheInitDbCommandFactory(): void
    {
        $deps = $this->provider->getDependencies();

        $this->assertSame(InitDbCommandFactory::class, $deps['factories'][InitDbCommand::class]);
    }

    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function getListenersPsr14ListenerHasPriority(): void
    {
        $listeners = $this->provider->getListeners();

        $this->assertArrayHasKey('priority', $listeners[LogEvent::class][0]);
        $this->assertIsInt($listeners[LogEvent::class][0]['priority']);
    }

    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function getListenersRegistersLogEventWithPsr14Listener(): void
    {
        $listeners = $this->provider->getListeners();

        $this->assertArrayHasKey(LogEvent::class, $listeners);
        $this->assertSame(Psr3LogPsr14Listener::class, $listeners[LogEvent::class][0]['listener']);
    }

    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function invokeContainsListenerKey(): void
    {
        $config = ($this->provider)();

        $this->assertArrayHasKey(ConfigProvider::LISTENER_KEY, $config);
    }

    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function invokeContainsListenerProviderKey(): void
    {
        $config = ($this->provider)();

        $this->assertArrayHasKey(ConfigProvider::LISTENER_PROVIDER_KEY, $config);
    }

    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function invokeDoesNotContainLegacyConfigProviderKey(): void
    {
        $config = ($this->provider)();

        $this->assertArrayNotHasKey(ConfigProvider::class, $config);
    }

    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function invokeDoesNotContainLogRuntime(): void
    {
        $config = ($this->provider)();

        $this->assertArrayNotHasKey('log_runtime', $config);
    }

    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function invokeNestedLoggerConfigMatchesGetConfigDefaults(): void
    {
        $config = ($this->provider)();

        $this->assertSame($this->provider->getConfigDefaults(), $config[LoggerInterface::class]);
    }

    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function invokeRegistersTheLogInitDbCommandForConsoleDiscovery(): void
    {
        $config = ($this->provider)();

        $this->assertSame(
            ['commands' => ['log:init-db' => InitDbCommand::class]],
            $config[ConsoleInterface::class],
        );
    }

    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function invokeReturnsArrayKeyedOnLoggerInterface(): void
    {
        $config = ($this->provider)();

        $this->assertArrayHasKey(LoggerInterface::class, $config);
    }

    #[Override]
    protected function setUp(): void
    {
        $this->provider = new ConfigProvider();
    }
}
