<?php

declare(strict_types=1);

namespace WebwareTest\Log\Console\Ddl\Column;

use PhpDb\Adapter\Driver\ConnectionInterface;
use PhpDb\Adapter\Driver\DriverInterface;
use PhpDb\Mysql\AdapterPlatform;
use PhpDb\Mysql\Sql\Platform;
use PhpDb\Sql\Ddl\CreateTable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\Log\Console\Ddl\Column\LongText;

#[CoversClass(LongText::class)]
final class LongTextTest extends TestCase
{
    #[Test]
    public function rendersAsLongtext(): void
    {
        $table = new CreateTable(table: 'probe');
        $table->addColumn(new LongText(
            name    : 'message',
            nullable: false,
        ));

        $driver     = $this->createStub(DriverInterface::class);
        $connection = $this->createStub(ConnectionInterface::class);
        $driver->method('getConnection')->willReturn($connection);

        $platform = new Platform();
        $platform->setSubject($table);

        self::assertStringContainsString(
            '`message` LONGTEXT NOT NULL',
            $platform->getSqlString(new AdapterPlatform($driver)),
        );
    }
}
