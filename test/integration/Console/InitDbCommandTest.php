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

namespace WebwareTestIntegration\Log\Console;

use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use Override;
use PDO;
use PhpDb\Adapter\Adapter;
use PhpDb\Adapter\AdapterInterface;
use PhpDb\Adapter\Driver\Pdo\Result;
use PhpDb\Adapter\Driver\Pdo\Statement;
use PhpDb\Mysql\AdapterPlatform;
use PhpDb\Mysql\Pdo\Connection;
use PhpDb\Mysql\Pdo\Driver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Webware\Log\Console\InitDbCommand;
use Webware\Log\Handler\PhpDbHandler;

use function getenv;
use function sprintf;

#[CoversClass(InitDbCommand::class)]
#[CoversMethod(InitDbCommand::class, 'execute')]
#[RequiresPhpExtension('pdo_mysql')]
final class InitDbCommandTest extends TestCase
{
    private const string TABLE = 'init_db_probe';

    private AdapterInterface $adapter;

    private PDO $pdo;

    #[Test]
    public function createdTableMatchesTheFixtureTheHandlerIsTestedAgainst(): void
    {
        $this->runCommand(['--drop' => true]);

        self::assertSame(
            $this->describe('log'),
            $this->describe(self::TABLE),
        );
    }

    #[Test]
    public function handlerWritesToTheCreatedTable(): void
    {
        $this->runCommand(['--drop' => true]);

        new PhpDbHandler($this->adapter, self::TABLE)->handle(new LogRecord(
            datetime : new DateTimeImmutable(),
            channel  : 'app',
            level    : Level::Info,
            message  : 'written to the created table',
            formatted: 'written to the created table',
        ));

        self::assertSame(
            '1',
            (string) $this->pdo->query(sprintf('SELECT COUNT(*) FROM `%s`', self::TABLE))?->fetchColumn(),
        );
    }

    #[Test]
    public function runningWithDropEmptiesTheTable(): void
    {
        $this->runCommand(['--drop' => true]);
        $this->pdo->exec(sprintf(
            "INSERT INTO `%s` (channel, level, message, time) VALUES ('app', 'INFO', 'gone', 1)",
            self::TABLE,
        ));

        $this->runCommand(['--drop' => true]);

        self::assertSame(
            '0',
            (string) $this->pdo->query(sprintf('SELECT COUNT(*) FROM `%s`', self::TABLE))?->fetchColumn(),
        );
    }

    #[Test]
    public function runningWithoutDropKeepsExistingRows(): void
    {
        $this->runCommand(['--drop' => true]);
        $this->pdo->exec(sprintf(
            "INSERT INTO `%s` (channel, level, message, time) VALUES ('app', 'INFO', 'kept', 1)",
            self::TABLE,
        ));

        self::assertSame(Command::SUCCESS, $this->runCommand([]));
        self::assertSame(
            '1',
            (string) $this->pdo->query(sprintf('SELECT COUNT(*) FROM `%s`', self::TABLE))?->fetchColumn(),
        );
    }

    #[Override]
    protected function setUp(): void
    {
        $hostname = $this->env('TESTS_ADAPTER_MYSQL_HOSTNAME', 'localhost');
        $username = $this->env('TESTS_ADAPTER_MYSQL_USERNAME', 'root');
        $password = $this->env('TESTS_ADAPTER_MYSQL_PASSWORD', '');
        $database = $this->env('TESTS_ADAPTER_MYSQL_DATABASE', 'webware_log_test');
        $port     = (int) $this->env('TESTS_ADAPTER_MYSQL_PORT', '3306');

        $driver = new Driver(
            new Connection([
                'hostname' => $hostname,
                'port'     => $port,
                'username' => $username,
                'password' => $password,
                'database' => $database,
            ]),
            new Statement(),
            new Result(),
        );
        $this->adapter = new Adapter($driver, new AdapterPlatform($driver));

        $this->pdo = new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $hostname, $port, $database),
            $username,
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->pdo->exec(sprintf('DROP TABLE IF EXISTS `%s`', self::TABLE));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function describe(string $table): array
    {
        $statement = $this->pdo->prepare(
            'SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_KEY, EXTRA, COLLATION_NAME'
                . ' FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
                . ' ORDER BY ORDINAL_POSITION',
        );
        $statement->execute([$table]);

        /** @var list<array<string, mixed>> */
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function env(string $name, string $default): string
    {
        $value = getenv($name);

        return false === $value ? $default : $value;
    }

    /**
     * @param array<string, bool> $input
     */
    private function runCommand(array $input): int
    {
        $tester = new CommandTester(new InitDbCommand(
            adapter: $this->adapter,
            table  : self::TABLE,
        ));
        $tester->execute($input);

        return $tester->getStatusCode();
    }
}
