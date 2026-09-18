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

namespace Webware\Log\Listener;

use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventInterface;
use Laminas\EventManager\EventManagerInterface;
use Monolog\Level;
use Monolog\Logger;
use Override;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Stringable;
use Webware\Log\Event\LogEvent;
use Webware\Log\Http\PipelineIdentifiers;
use Webware\Log\LogChannel;

use function assert;

/**
 * @deprecated since 0.1.0; will be removed in 0.2.0. Use Psr3LogPsr14Listener instead.
 */
final class Psr3LogLaminasListener extends AbstractListenerAggregate
{
    /** @var list<class-string> */
    private array $identifiers = PipelineIdentifiers::ALL;

    public function __construct(
        private LoggerInterface&Logger $logger,
    ) {}

    #[Override]
    public function attach(EventManagerInterface $events, mixed $priority = 1): void
    {
        $sharedEvents = $events->getSharedManager();
        if (null === $sharedEvents) {
            return;
        }

        foreach ($this->identifiers as $identifier) {
            $sharedEvents->attach($identifier, LogEvent::EVENT_LOG, [$this, 'onLog']);
        }

        foreach (Level::cases() as $level) {
            foreach ($this->identifiers as $identifier) {
                $sharedEvents->attach($identifier, $level->toPsrLogLevel(), [$this, 'onLog']);
            }
        }
    }

    /**
     * @param EventInterface<object, array<string, mixed>> $event
     * @throws InvalidArgumentException
     */
    public function onLog(EventInterface $event): void
    {
        $channel = $event->getParam('channel', LogChannel::App);
        assert($channel instanceof LogChannel);

        if (LogChannel::App !== $channel) {
            $this->logger = $this->logger->withName($channel->value);
        }

        $level = $event->getParam('level');
        assert($level instanceof Level);

        /** @var string|Stringable $message */
        $message = $event->getParam('message');

        /** @var array<mixed> $context */
        $context = $event->getParam('context', []);

        $this->logger->log(
            $level->toPsrLogLevel(),
            $message,
            $context,
        );
    }
}
