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

namespace Webware\Log\Processor;

use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\Translator\TranslatorInterface;
use Monolog\Processor\ProcessorInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

final class LaminasI18nProcessorFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container): ProcessorInterface
    {
        if (! $container->has(TranslatorInterface::class)) {
            throw new ServiceNotFoundException(TranslatorInterface::class . ' was not found in the container');
        }

        $translator = $container->get(TranslatorInterface::class);

        return new LaminasI18nProcessor($translator);
    }
}
