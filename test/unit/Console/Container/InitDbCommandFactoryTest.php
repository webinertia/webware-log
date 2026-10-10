<?php

declare(strict_types=1);

namespace WebwareTest\Log\Console\Container;

use PhpDb\Adapter\AdapterInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Webware\Log\Console\Container\InitDbCommandFactory;
use Webware\Log\Console\InitDbCommand;
use WebwareTest\Log\Support\DdlAdapterTrait;

#[CoversClass(InitDbCommandFactory::class)]
#[CoversMethod(InitDbCommandFactory::class, '__invoke')]
final class InitDbCommandFactoryTest extends TestCase
{
    use DdlAdapterTrait;

    #[Test]
    public function invokeFallsBackToTheDefaultLogTable(): void
    {
        $executed = [];
        $command  = new InitDbCommandFactory()($this->container(
            config  : [],
            executed: $executed,
        ));

        self::assertInstanceOf(InitDbCommand::class, $command);

        new CommandTester($command)->execute([]);

        self::assertStringContainsString('`log`', $executed[0]);
    }

    #[Test]
    public function invokeUsesTheTableTheHandlerIsConfiguredWith(): void
    {
        $executed = [];
        $command  = new InitDbCommandFactory()($this->container(
            config  : [LoggerInterface::class => ['table' => 'app_log']],
            executed: $executed,
        ));

        new CommandTester($command)->execute([]);

        self::assertStringContainsString('`app_log`', $executed[0]);
    }

    /**
     * @param array<string, mixed> $config
     * @param list<string> $executed
     */
    private function container(array $config, array &$executed): ContainerInterface
    {
        $adapter   = $this->ddlAdapter($executed);
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')
            ->willReturnCallback(
                static fn(string $id): mixed => match ($id) {
                    'config'                => $config,
                    AdapterInterface::class => $adapter,
                },
            );

        return $container;
    }
}
