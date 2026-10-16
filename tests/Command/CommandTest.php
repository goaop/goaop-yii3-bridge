<?php
/*
 * Go! AOP framework
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace Go\Yii3\GoAopBridge\Tests\Command;

use Go\Yii3\GoAopBridge\Command\DebugAdvisorCommand;
use Go\Yii3\GoAopBridge\Command\DebugAspectCommand;
use Go\Yii3\GoAopBridge\Command\DebugWeavingCommand;
use Go\Yii3\GoAopBridge\Command\WarmupCommand;
use Go\Yii3\GoAopBridge\Tests\App\Aspect\GreetingAspect;
use Go\Yii3\GoAopBridge\Tests\Weaving\WeavingTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The commands get the aspect kernel injected by the Yii container
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class CommandTest extends WeavingTestCase
{
    /**
     * @return iterable<string, array{class-string<Command>, string}>
     */
    public static function commands(): iterable
    {
        yield 'warmup' => [WarmupCommand::class, 'aop:warmup'];
        yield 'debug aspect' => [DebugAspectCommand::class, 'aop:debug:aspect'];
        yield 'debug advisor' => [DebugAdvisorCommand::class, 'aop:debug:advisor'];
        yield 'debug weaving' => [DebugWeavingCommand::class, 'aop:debug:weaving'];
    }

    /**
     * @param class-string<Command> $commandClass
     */
    #[DataProvider('commands')]
    public function testCommandIsNamedInTheAopNamespaceAndNeedsNoLoaderArgument(string $commandClass, string $name): void
    {
        $command = $this->command($commandClass);

        $this->assertSame($name, $command->getName());
        $this->assertNotSame('', $command->getDescription());
        $this->assertFalse($command->getDefinition()->hasArgument('loader'));
    }

    public function testWarmupWeavesTheApplicationIntoTheCache(): void
    {
        $tester = new CommandTester($this->command(WarmupCommand::class));

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertStringContainsString('0 errors', $tester->getDisplay());
        $this->assertTrue($this->cacheContainsFileFor('GreetingService'));
        $this->assertTrue($this->cacheContainsFileFor('ReportRepository'));
    }

    public function testDebugAspectShowsOneAspect(): void
    {
        $tester = new CommandTester($this->command(DebugAspectCommand::class));

        $this->assertSame(Command::SUCCESS, $tester->execute(['--aspect' => GreetingAspect::class]));
        $this->assertStringContainsString(GreetingAspect::class . '->aroundShout', $tester->getDisplay());
    }

    public function testDebugAdvisorListsTheAdvisors(): void
    {
        $tester = new CommandTester($this->command(DebugAdvisorCommand::class));

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertStringContainsString(GreetingAspect::class . '->beforeGreet', $tester->getDisplay());
    }

    public function testDebugWeavingFindsNoInconsistency(): void
    {
        $tester = new CommandTester($this->command(DebugWeavingCommand::class));

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
    }

    /**
     * @param class-string<Command> $commandClass
     */
    private function command(string $commandClass): Command
    {
        $command = $this->startApplication()->get($commandClass);
        $this->assertInstanceOf($commandClass, $command);

        return $command;
    }
}
