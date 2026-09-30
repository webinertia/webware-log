<?php

declare(strict_types=1);

namespace WebwareTest\Log\Console;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Webware\Log\Console\InitDbCommand;
use WebwareTest\Log\Support\DdlAdapterTrait;

use function count;

#[CoversClass(InitDbCommand::class)]
#[CoversMethod(InitDbCommand::class, 'configure')]
#[CoversMethod(InitDbCommand::class, 'execute')]
final class InitDbCommandTest extends TestCase
{
    use DdlAdapterTrait;

    #[Test]
    public function commandIsNamedLogInitDb(): void
    {
        $executed = [];
        $command  = new InitDbCommand(
            adapter: $this->ddlAdapter($executed),
            table  : 'log',
        );

        self::assertSame('log:init-db', $command->getName());
        self::assertTrue($command->getDefinition()->hasOption('drop'));
    }

    #[Test]
    public function executeCreatesTheLogTable(): void
    {
        $executed = [];
        $command  = new InitDbCommand(
            adapter: $this->ddlAdapter($executed),
            table  : 'log',
        );

        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertCount(1, $executed);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `log`', $executed[0]);
        self::assertStringContainsString('Creating table log...', $tester->getDisplay());
        self::assertStringContainsString('Log table initialized.', $tester->getDisplay());
        self::assertStringNotContainsString('Dropping table', $tester->getDisplay());
    }

    #[Test]
    public function executeDropsTheTableFirstWhenRequested(): void
    {
        $executed = [];
        $command  = new InitDbCommand(
            adapter: $this->ddlAdapter($executed),
            table  : 'log',
        );

        $tester = new CommandTester($command);
        $tester->execute(['--drop' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertCount(2, $executed);
        self::assertStringContainsString('DROP TABLE IF EXISTS `log`', $executed[0]);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `log`', $executed[1]);
        self::assertStringContainsString('Dropping table log...', $tester->getDisplay());
    }

    #[Test]
    public function executeUsesTheConfiguredTableName(): void
    {
        $executed = [];
        $command  = new InitDbCommand(
            adapter: $this->ddlAdapter($executed),
            table  : 'app_log',
        );

        new CommandTester($command)->execute([]);

        self::assertSame(1, count($executed));
        self::assertStringContainsString('`app_log`', $executed[0]);
    }
}
