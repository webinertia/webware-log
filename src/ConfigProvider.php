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

namespace Webware\Log;

use Laminas\Stratigility\Middleware\ErrorHandler;
use Mezzio\Authentication\UserInterface;
use Phly\EventDispatcher\EventDispatcher;
use Phly\EventDispatcher\ListenerProvider\AttachableListenerProvider;
use Phly\EventDispatcher\ListenerProvider\ListenerProviderAggregate;
use Phly\EventDispatcher\ListenerProvider\PrioritizedListenerProvider;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\Log\LoggerInterface;

/**
 * @phpstan-type LogDefaults array{
 *     auth_attribute: string,
 *     channel: string,
 *     log_errors: bool,
 *     process_uuid: bool,
 *     process_translation: bool,
 *     table: string,
 * }
 * @phpstan-type LogAliases array{
 *     EventDispatcherInterface::class: class-string,
 *     ListenerProviderInterface::class: class-string,
 * }
 * @phpstan-type LogDelegators array{
 *     ErrorHandler::class: list<class-string>,
 * }
 * @phpstan-type LogFactories array{
 *     ListenerProviderAggregate::class: class-string,
 *     Listener\Psr3LogPsr14Listener::class: class-string,
 *     LoggerInterface::class: class-string,
 *     Http\Middleware\MonologMiddleware::class: class-string,
 *     Handler\PhpDbHandler::class: class-string,
 *     Processor\LaminasI18nProcessor::class: class-string,
 * }
 * @phpstan-type LogInvokables array{
 *     AttachableListenerProvider::class: class-string,
 *     PrioritizedListenerProvider::class: class-string,
 *     Processor\RamseyUuidProcessor::class: class-string,
 * }
 * @phpstan-type LogDependencies array{
 *     aliases: LogAliases,
 *     delegators: LogDelegators,
 *     factories: LogFactories,
 *     invokables: LogInvokables,
 * }
 * @phpstan-type LogListenerEntry array{listener: class-string, priority: int}
 * @phpstan-type LogListeners array<class-string, list<LogListenerEntry>>
 * @phpstan-type LogTemplatePaths array{paths: array{log: list<string>}}
 * @phpstan-type LogConfig array{
 *     dependencies: LogDependencies,
 *     listeners: LogListeners,
 *     listener_providers: array<empty>,
 *     templates: LogTemplatePaths,
 *     LoggerInterface::class: LogDefaults,
 * }
 */
class ConfigProvider
{
    public const string LISTENER_KEY = 'listeners';

    public const string LISTENER_PROVIDER_KEY = 'listener_providers';

    /** @return LogDefaults */
    public function getConfigDefaults(): array
    {
        return [
            'auth_attribute'      => UserInterface::class,
            'channel'             => LogChannel::App->value,
            'log_errors'          => false,
            'process_uuid'        => false,
            'process_translation' => false,
            'table'               => 'log',
        ];
    }

    /** @return LogDependencies */
    public function getDependencies(): array
    {
        return [
            'aliases'    => [
                EventDispatcherInterface::class  => EventDispatcher::class,
                ListenerProviderInterface::class => ListenerProviderAggregate::class,
            ],
            'delegators' => [
                ErrorHandler::class => [
                    Container\MezzioErrorHandlerDelegator::class,
                ],
            ],
            'factories'  => [
                ListenerProviderAggregate::class => Container\ListenerProviderAggregateFactory::class,

                Listener\Psr3LogPsr14Listener::class     => Listener\Container\Psr3LogPsr14ListenerFactory::class,
                LoggerInterface::class                   => Container\LogFactory::class,
                Http\Middleware\MonologMiddleware::class => Http\Middleware\Container\MonologMiddlewareFactory::class,
                Handler\PhpDbHandler::class              => Handler\PhpDbHandlerFactory::class,
                Processor\LaminasI18nProcessor::class    => Processor\LaminasI18nProcessorFactory::class,
            ],
            'invokables' => [
                AttachableListenerProvider::class    => AttachableListenerProvider::class,
                PrioritizedListenerProvider::class   => PrioritizedListenerProvider::class,
                Processor\RamseyUuidProcessor::class => Processor\RamseyUuidProcessor::class,
            ],
        ];
    }

    /** @return LogListeners */
    public function getListeners(): array
    {
        return [
            Event\LogEvent::class => [
                ['listener' => Listener\Psr3LogPsr14Listener::class, 'priority' => 1],
            ],
        ];
    }

    /** @return array<int, array{middleware: list<class-string>}> */
    public function getPipelineConfig(): array
    {
        return [
            [
                'middleware' => [
                    Http\Middleware\MonologMiddleware::class,
                ],
                // 'priority'   => 9000,
            ],
        ];
    }

    /** @return LogTemplatePaths */
    public function getTemplates(): array
    {
        return [
            'paths' => [
                'log' => [__DIR__ . '/../templates/'],
            ],
        ];
    }

    /** @return LogConfig */
    public function __invoke(): array
    {
        return [
            'dependencies'              => $this->getDependencies(),
            self::LISTENER_KEY          => $this->getListeners(),
            self::LISTENER_PROVIDER_KEY => [],
            // 'middleware_pipeline' => $this->getPipelineConfig(),
            'templates'            => $this->getTemplates(),
            LoggerInterface::class => $this->getConfigDefaults(),
        ];
    }
}
