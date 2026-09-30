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

namespace Webware\Log\Console\Container;

use PhpDb\Adapter\AdapterInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Exception\LogicException;
use Webware\Log\ConfigProvider;
use Webware\Log\Console\InitDbCommand;

/**
 * The table name is read the way the database handler reads it (the configured logger table, else
 * the default), so the command creates the table the handler will write to.
 */
final readonly class InitDbCommandFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws LogicException
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container): InitDbCommand
    {
        /** @var array<string, array{table?: string}> $config */
        $config = $container->get('config');

        return new InitDbCommand(
            adapter: $container->get(AdapterInterface::class),
            table  : $config[LoggerInterface::class]['table'] ?? new ConfigProvider()->getConfigDefaults()['table'],
        );
    }
}
