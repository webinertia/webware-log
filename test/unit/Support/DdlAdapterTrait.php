<?php

declare(strict_types=1);

namespace WebwareTest\Log\Support;

use PhpDb\Adapter\AdapterInterface;
use PhpDb\Adapter\Driver\ConnectionInterface;
use PhpDb\Adapter\Driver\DriverInterface;
use PhpDb\Adapter\Driver\ResultInterface;
use PhpDb\Mysql\AdapterPlatform;

/**
 * An adapter that accepts DDL: it renders through the real MySQL platform and records what it was
 * asked to execute, so a test can assert the statements without a database.
 */
trait DdlAdapterTrait
{
    /**
     * @param list<string> $executed filled with each statement the adapter is asked to run
     */
    private function ddlAdapter(array &$executed): AdapterInterface
    {
        $driver     = $this->createStub(DriverInterface::class);
        $connection = $this->createStub(ConnectionInterface::class);
        $driver->method('getConnection')->willReturn($connection);

        $adapter = $this->createStub(AdapterInterface::class);
        $adapter->method('getDriver')->willReturn($driver);
        $adapter->method('getPlatform')->willReturn(new AdapterPlatform($driver));
        $adapter->method('query')
            ->willReturnCallback(
                function (string $sql) use (&$executed): ResultInterface {
                    $executed[] = $sql;

                    return $this->createStub(ResultInterface::class);
                },
            );

        return $adapter;
    }
}
