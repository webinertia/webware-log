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

use Monolog\Logger;
use Phly\EventDispatcher\ListenerProvider\AttachableListenerProvider;
use Phly\EventDispatcher\ListenerProvider\ListenerProviderAggregate;
use Phly\EventDispatcher\ListenerProvider\PrioritizedListenerProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Webware\Log\ConfigProvider;
use Webware\Log\Container\ListenerProviderAggregateFactory;
use Webware\Log\Event\LogEvent;
use Webware\Log\Listener\Psr3LogPsr14Listener;

#[CoversClass(ListenerProviderAggregateFactory::class)]
#[CoversMethod(ListenerProviderAggregateFactory::class, '__invoke')]
final class ListenerProviderAggregateFactoryTest extends TestCase
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function invokeAttachesConfiguredListenerProviders(): void
    {
        $listener = static function (object $event): void {};

        $provider = $this->createStub(ListenerProviderInterface::class);
        $provider->method('getListenersForEvent')->willReturn([$listener]);

        $container = $this->makeContainer(
            [ConfigProvider::LISTENER_PROVIDER_KEY => [ListenerProviderInterface::class]],
            [ListenerProviderInterface::class => $provider],
        );

        $factory = new ListenerProviderAggregateFactory();
        $result  = $factory($container);

        $this->assertContains($listener, $result->getListenersForEvent(new LogEvent()));
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function invokeExposesListenersFromAttachableProvider(): void
    {
        $listener   = static function (object $event): void {};
        $attachable = new AttachableListenerProvider();
        $attachable->listen(LogEvent::class, $listener);

        $container = $this->makeContainer([], attachableProvider: $attachable);

        $factory = new ListenerProviderAggregateFactory();
        $result  = $factory($container);

        $this->assertContains($listener, $result->getListenersForEvent(new LogEvent()));
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function invokeHandlesEmptyListenersConfig(): void
    {
        $container = $this->makeContainer([
            ConfigProvider::LISTENER_KEY          => [],
            ConfigProvider::LISTENER_PROVIDER_KEY => [],
        ]);

        $factory = new ListenerProviderAggregateFactory();
        $result  = $factory($container);

        $this->assertInstanceOf(ListenerProviderAggregate::class, $result);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function invokeProcessesEveryListenerInASpec(): void
    {
        $logger   = $this->createStub(Logger::class);
        $listener = new Psr3LogPsr14Listener($logger);

        $prioritizedProvider = $this->createMock(PrioritizedListenerProvider::class);
        $prioritizedProvider->expects($this->once())->method('listen');

        $attachableProvider = $this->createMock(AttachableListenerProvider::class);
        $attachableProvider->expects($this->once())->method('listen');

        $container = $this->makeContainer(
            [
                ConfigProvider::LISTENER_KEY => [
                    LogEvent::class => [
                        ['listener' => Psr3LogPsr14Listener::class, 'priority' => 1],
                        ['listener' => Psr3LogPsr14Listener::class, 'priority' => 0],
                    ],
                ],
            ],
            [Psr3LogPsr14Listener::class => $listener],
            prioritizedProvider: $prioritizedProvider,
            attachableProvider: $attachableProvider,
        );

        $factory = new ListenerProviderAggregateFactory();
        $result  = $factory($container);

        $this->assertInstanceOf(ListenerProviderAggregate::class, $result);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function invokeReturnsListenerProviderAggregate(): void
    {
        $container = $this->makeContainer([]);
        $factory   = new ListenerProviderAggregateFactory();
        $result    = $factory($container);

        $this->assertInstanceOf(ListenerProviderAggregate::class, $result);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function invokeUsesAttachableProviderWhenPriorityIsZero(): void
    {
        // priority 0 counts as "no priority" because the guard uses ! empty(); this pins that behaviour.
        $logger   = $this->createStub(Logger::class);
        $listener = new Psr3LogPsr14Listener($logger);

        $prioritizedProvider = $this->createMock(PrioritizedListenerProvider::class);
        $prioritizedProvider->expects($this->never())->method('listen');

        $attachableProvider = $this->createMock(AttachableListenerProvider::class);
        $attachableProvider->expects($this->once())->method('listen');

        $container = $this->makeContainer(
            [
                ConfigProvider::LISTENER_KEY => [
                    LogEvent::class => [
                        ['listener' => Psr3LogPsr14Listener::class, 'priority' => 0],
                    ],
                ],
            ],
            [Psr3LogPsr14Listener::class => $listener],
            prioritizedProvider: $prioritizedProvider,
            attachableProvider: $attachableProvider,
        );

        $factory = new ListenerProviderAggregateFactory();
        $result  = $factory($container);

        $this->assertInstanceOf(ListenerProviderAggregate::class, $result);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function invokeUsesPrioritizedProviderWhenListenerHasPriority(): void
    {
        $logger   = $this->createStub(Logger::class);
        $listener = new Psr3LogPsr14Listener($logger);

        $prioritizedProvider = $this->createMock(PrioritizedListenerProvider::class);
        $prioritizedProvider->expects($this->once())->method('listen');

        $attachableProvider = $this->createMock(AttachableListenerProvider::class);
        $attachableProvider->expects($this->never())->method('listen');

        $container = $this->makeContainer(
            [
                ConfigProvider::LISTENER_KEY => [
                    LogEvent::class => [
                        ['listener' => Psr3LogPsr14Listener::class, 'priority' => 1],
                    ],
                ],
            ],
            [Psr3LogPsr14Listener::class => $listener],
            prioritizedProvider: $prioritizedProvider,
            attachableProvider: $attachableProvider,
        );

        $factory = new ListenerProviderAggregateFactory();
        $result  = $factory($container);

        $this->assertInstanceOf(ListenerProviderAggregate::class, $result);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function invokeWiresListenerFromContainerWithPriority(): void
    {
        $logger   = $this->createStub(Logger::class);
        $listener = new Psr3LogPsr14Listener($logger);

        $container = $this->makeContainer(
            [
                ConfigProvider::LISTENER_KEY => [
                    LogEvent::class => [
                        ['listener' => Psr3LogPsr14Listener::class, 'priority' => 1],
                    ],
                ],
            ],
            [Psr3LogPsr14Listener::class => $listener],
        );

        $factory = new ListenerProviderAggregateFactory();
        $result  = $factory($container);

        $this->assertInstanceOf(ListenerProviderAggregate::class, $result);

        // iterator_to_array()/spread against a broken vendor iterable docblock trips mago; collect manually instead.
        $listeners = [];
        foreach ($result->getListenersForEvent(new LogEvent()) as $registeredListener) {
            $listeners[] = $registeredListener;
        }
        $this->assertSame([$listener], $listeners);
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $services
     * @throws \PHPUnit\Exception
     */
    private function makeContainer(
        array $config,
        array $services = [],
        ?PrioritizedListenerProvider $prioritizedProvider = null,
        ?AttachableListenerProvider $attachableProvider = null,
    ): ContainerInterface {
        $services[PrioritizedListenerProvider::class] = $prioritizedProvider ?? new PrioritizedListenerProvider();
        $services[AttachableListenerProvider::class]  = $attachableProvider ?? new AttachableListenerProvider();

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
