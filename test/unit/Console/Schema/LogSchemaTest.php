<?php

declare(strict_types=1);

namespace WebwareTest\Log\Console\Schema;

use PhpDb\Adapter\Driver\ConnectionInterface;
use PhpDb\Adapter\Driver\DriverInterface;
use PhpDb\Mysql\AdapterPlatform;
use PhpDb\Mysql\Sql\Platform;
use PhpDb\Sql\Ddl\Column\ColumnInterface;
use PhpDb\Sql\Ddl\Constraint\ConstraintInterface;
use PhpDb\Sql\Ddl\Constraint\PrimaryKey;
use PhpDb\Sql\Ddl\Constraint\UniqueKey;
use PhpDb\Sql\Ddl\CreateTable;
use PhpDb\Sql\Ddl\DropTable;
use PhpDb\Sql\Ddl\Index\Index;
use PhpDb\Sql\Literal;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webware\Log\Console\Schema\LogSchema;

use function array_keys;
use function array_map;
use function preg_replace;

#[CoversClass(LogSchema::class)]
#[CoversMethod(LogSchema::class, 'logTable')]
#[CoversMethod(LogSchema::class, 'dropTable')]
final class LogSchemaTest extends TestCase
{
    #[Test]
    public function dropTableRendersDropIfExists(): void
    {
        $sql = $this->renderSql(new LogSchema()->dropTable('log'));

        self::assertStringContainsString('DROP TABLE IF EXISTS `log`', $sql);
    }

    #[Test]
    public function logTableCarriesTheKeysAndTheChannelIndex(): void
    {
        $constraints = $this->constraints(new LogSchema()->logTable('log'));

        self::assertCount(3, $constraints);
        self::assertInstanceOf(PrimaryKey::class, $constraints[0]);
        self::assertInstanceOf(UniqueKey::class, $constraints[1]);
        self::assertSame('uuid', $constraints[1]->getName());
        self::assertInstanceOf(Index::class, $constraints[2]);
        self::assertSame('ChannelIndex', $constraints[2]->getName());
    }

    #[Test]
    public function logTableHasExactlyTheColumnsTheHandlerInserts(): void
    {
        $names = array_map(
            static fn(ColumnInterface $column): string => $column->getName(),
            $this->columns(new LogSchema()->logTable('log')),
        );

        self::assertSame(
            ['id', 'uuid', 'channel', 'level', 'user_identifier', 'message', 'time', 'context'],
            $names,
        );
    }

    #[Test]
    public function logTableOptionsSetEngineCharsetAndCollation(): void
    {
        $options = new LogSchema()->logTable('log')
            ->getOptions();

        self::assertSame(['engine', 'default charset', 'collate'], array_keys($options));
        self::assertInstanceOf(Literal::class, $options['engine']);
        self::assertSame('InnoDB', $options['engine']->getLiteral());
        self::assertSame('utf8mb4', $options['default charset']->getLiteral());
        self::assertSame('utf8mb4_general_ci', $options['collate']->getLiteral());
    }

    #[Test]
    public function logTableRendersTheExactStatement(): void
    {
        $sql = (string) preg_replace(
            pattern    : '/\s+/',
            replacement: ' ',
            subject    : $this->renderSql(new LogSchema()->logTable('log')),
        );

        self::assertSame(
            'CREATE TABLE IF NOT EXISTS `log` ( '
                . '`id` INTEGER UNSIGNED NOT NULL AUTO_INCREMENT, '
                . '`uuid` CHAR(36) NULL DEFAULT NULL, '
                . '`channel` VARCHAR(255) NOT NULL, '
                . '`level` VARCHAR(9) NOT NULL, '
                . '`user_identifier` VARCHAR(320) NULL DEFAULT NULL, '
                . '`message` LONGTEXT NOT NULL, '
                . '`time` INTEGER UNSIGNED NOT NULL, '
                . '`context` LONGTEXT NULL DEFAULT NULL , '
                . 'PRIMARY KEY (`id`), '
                . 'CONSTRAINT `uuid` UNIQUE (`uuid`), '
                . 'INDEX `ChannelIndex`(`channel`) '
                . ') ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci',
            $sql,
        );
    }

    #[Test]
    public function logTableRendersValidMysqlDdl(): void
    {
        $sql = $this->renderSql(new LogSchema()->logTable('log'));

        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `log`', $sql);
        self::assertStringContainsString('UNSIGNED', $sql);
        self::assertStringContainsString('AUTO_INCREMENT', $sql);
        self::assertStringContainsString('CHAR(36)', $sql);
        self::assertStringContainsString('VARCHAR(255)', $sql);
        self::assertStringContainsString('VARCHAR(9)', $sql);
        self::assertStringContainsString('VARCHAR(320)', $sql);
        self::assertStringContainsString('LONGTEXT', $sql);
        self::assertStringContainsString('PRIMARY KEY', $sql);
        self::assertStringContainsString('UNIQUE', $sql);
        self::assertStringContainsString('ChannelIndex', $sql);
        self::assertStringContainsString('ENGINE = InnoDB', $sql);
        self::assertStringContainsString('utf8mb4_general_ci', $sql);
    }

    #[Test]
    public function logTableUsesTheTableNameItIsGiven(): void
    {
        $sql = $this->renderSql(new LogSchema()->logTable('app_log'));

        self::assertStringContainsString('`app_log`', $sql);
    }

    /**
     * @return list<ColumnInterface>
     */
    private function columns(CreateTable $table): array
    {
        /** @var list<ColumnInterface> */
        return $table->getRawState('columns');
    }

    /**
     * @return list<ConstraintInterface>
     */
    private function constraints(CreateTable $table): array
    {
        /** @var list<ConstraintInterface> */
        return $table->getRawState('constraints');
    }

    private function renderSql(CreateTable|DropTable $sql): string
    {
        $driver     = $this->createStub(DriverInterface::class);
        $connection = $this->createStub(ConnectionInterface::class);
        $driver->method('getConnection')->willReturn($connection);

        $platform = new Platform();
        $platform->setSubject($sql);

        return $platform->getSqlString(new AdapterPlatform($driver));
    }
}
