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

namespace WebwareTestIntegration\Log\Handler;

use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use Override;
use PDO;
use PDOException;
use PDOStatement;
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
use Webware\Log\Handler\PhpDbHandler;

use function assert;
use function getenv;
use function sprintf;

#[CoversClass(PhpDbHandler::class)]
#[CoversMethod(PhpDbHandler::class, 'write')]
#[RequiresPhpExtension('pdo_mysql')]
final class PhpDbHandlerTest extends TestCase
{
    private AdapterInterface $adapter;

    private PDO $pdo;

    /**
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function bubbleDefaultsToTrue(): void
    {
        $handler = new PhpDbHandler($this->adapter, 'log');

        $this->assertTrue($handler->getBubble());
    }

    /**
     * @throws PDOException
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function writeExcludesExtraAuthIdentifierFromContextExtra(): void
    {
        $handler = new PhpDbHandler($this->adapter, 'log');
        $record  = new LogRecord(
            datetime : new DateTimeImmutable(),
            channel  : 'app',
            level    : Level::Info,
            message  : 'extra auth identifier exclusion test',
            formatted: 'extra auth identifier exclusion test',
            extra    : ['email' => 'user@example.com', 'request_id' => 'abc-123'],
        );

        $handler->handle($record);

        $stmt = $this->pdo->prepare(
            'SELECT context FROM log WHERE message = ? ORDER BY id DESC LIMIT 1',
        );
        $stmt->execute(['extra auth identifier exclusion test']);

        /** @var array{context: string}|false $row */
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($row);
        $this->assertStringContainsString('request_id', $row['context']);
        $this->assertStringNotContainsString('user@example.com', $row['context']);
    }

    /**
     * @throws PDOException
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function writeExcludesUuidFromContextExtra(): void
    {
        $handler = new PhpDbHandler($this->adapter, 'log');
        $record  = new LogRecord(
            datetime : new DateTimeImmutable(),
            channel  : 'app',
            level    : Level::Info,
            message  : 'extra exclusion test',
            formatted: 'extra exclusion test',
            extra    : ['uuid' => 'test-uuid-value', 'request_id' => 'abc-123'],
        );

        $handler->handle($record);

        $stmt = $this->pdo->prepare(
            'SELECT context FROM log WHERE message = ? ORDER BY id DESC LIMIT 1',
        );
        assert($stmt instanceof PDOStatement, description: 'PDO::prepare() must return a Statement for a valid query');
        $stmt->execute(['extra exclusion test']);

        /** @var array{context: string}|false $row */
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($row);
        $this->assertStringContainsString('request_id', $row['context']);
        $this->assertStringNotContainsString('uuid', $row['context']);
    }

    /**
     * @throws PDOException
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function writeInsertsLogRecord(): void
    {
        $handler = new PhpDbHandler($this->adapter, 'log');
        $record  = $this->makeRecord('integration test write');

        $handler->handle($record);

        $stmt = $this->pdo->prepare(
            'SELECT channel, level FROM log WHERE message = ? ORDER BY id DESC LIMIT 1',
        );
        $stmt->execute(['integration test write']);

        /** @var array{channel: string, level: string}|false $row */
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($row);
        $this->assertSame('app', $row['channel']);
        $this->assertSame('INFO', $row['level']);
    }

    /**
     * @throws PDOException
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function writePopulatesUserIdentifierWhenPresent(): void
    {
        $handler = new PhpDbHandler($this->adapter, 'log');
        $record  = new LogRecord(
            datetime : new DateTimeImmutable(),
            channel  : 'security',
            level    : Level::Warning,
            message  : 'user identifier test',
            formatted: 'user identifier test',
            extra    : ['email' => 'user@example.com'],
        );

        $handler->handle($record);

        $stmt = $this->pdo->prepare(
            'SELECT user_identifier FROM log WHERE message = ? ORDER BY id DESC LIMIT 1',
        );
        $stmt->execute(['user identifier test']);

        /** @var array{user_identifier: string}|false $row */
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($row);
        $this->assertSame('user@example.com', $row['user_identifier']);
    }

    /**
     * @throws PDOException
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function writePopulatesUuidWhenPresent(): void
    {
        $handler = new PhpDbHandler($this->adapter, 'log');
        $record  = new LogRecord(
            datetime : new DateTimeImmutable(),
            channel  : 'app',
            level    : Level::Debug,
            message  : 'uuid test',
            formatted: 'uuid test',
            extra    : ['uuid' => 'test-uuid-value'],
        );

        $handler->handle($record);

        $stmt = $this->pdo->prepare(
            'SELECT uuid FROM log WHERE message = ? ORDER BY id DESC LIMIT 1',
        );
        $stmt->execute(['uuid test']);

        /** @var array{uuid: string}|false $row */
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($row);
        $this->assertSame('test-uuid-value', $row['uuid']);
    }

    /**
     * @throws PDOException
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function writeSerializesContextToJson(): void
    {
        $handler = new PhpDbHandler($this->adapter, 'log');
        $record  = new LogRecord(
            datetime : new DateTimeImmutable(),
            channel  : 'app',
            level    : Level::Error,
            message  : 'context test',
            formatted: 'context test',
            context  : ['key' => 'value', 'url' => 'https://example.com/path', 'name' => 'café'],
        );

        $handler->handle($record);

        $stmt = $this->pdo->prepare(
            'SELECT context FROM log WHERE message = ? ORDER BY id DESC LIMIT 1',
        );
        $stmt->execute(['context test']);

        /** @var array{context: string}|false $row */
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($row);
        $this->assertStringContainsString('"key"', $row['context']);
        $this->assertStringContainsString('"value"', $row['context']);
        $this->assertStringContainsString('https://example.com/path', $row['context']);
        $this->assertStringContainsString('café', $row['context']);
    }

    /**
     * @throws PDOException
     * @throws \PHPUnit\Exception
     */
    #[Test]
    public function writeStoresNullContextWhenNothingIsLogged(): void
    {
        $handler = new PhpDbHandler($this->adapter, 'log');
        $record  = new LogRecord(
            datetime : new DateTimeImmutable(),
            channel  : 'app',
            level    : Level::Info,
            message  : 'empty context test',
            formatted: 'empty context test',
        );

        $handler->handle($record);

        $stmt = $this->pdo->prepare(
            'SELECT context FROM log WHERE message = ? ORDER BY id DESC LIMIT 1',
        );
        $stmt->execute(['empty context test']);

        /** @var array{context: string|null}|false $row */
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($row);
        $this->assertNull($row['context']);
    }

    /**
     * @throws PDOException
     */
    #[Override]
    protected function setUp(): void
    {
        $hostname = getenv('TESTS_ADAPTER_MYSQL_HOSTNAME') ?: 'localhost';
        $username = getenv('TESTS_ADAPTER_MYSQL_USERNAME') ?: 'webware';
        $password = getenv('TESTS_ADAPTER_MYSQL_PASSWORD') ?: 'webware';
        $database = getenv('TESTS_ADAPTER_MYSQL_DATABASE') ?: 'webware';
        $port     = (int) (getenv('TESTS_ADAPTER_MYSQL_PORT') ?: '3306');

        $connection = new Connection([
            'hostname' => $hostname,
            'port'     => $port,
            'username' => $username,
            'password' => $password,
            'database' => $database,
        ]);
        $driver        = new Driver($connection, new Statement(), new Result());
        $platform      = new AdapterPlatform($driver);
        $this->adapter = new Adapter($driver, $platform);

        $dsn       = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $hostname, $port, $database);
        $this->pdo = new PDO($dsn, $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec('TRUNCATE TABLE `log`');
    }

    private function makeRecord(string $message = 'test message'): LogRecord
    {
        return new LogRecord(
            datetime : new DateTimeImmutable(),
            channel  : 'app',
            level    : Level::Info,
            message  : $message,
            formatted: $message,
        );
    }
}
