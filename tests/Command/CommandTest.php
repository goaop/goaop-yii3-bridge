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
use Go\Yii3\GoAopBridge\Command\OpcacheWarmupCommand;
use Go\Yii3\GoAopBridge\Command\WarmupCommand;
use Go\Yii3\GoAopBridge\Command\WarmupWorkerCommand;
use Go\Yii3\GoAopBridge\Kernel\AspectYii3Kernel;
use Go\Aop\Exception\InvalidConfigurationException;
use Go\Yii3\GoAopBridge\Tests\App\Aspect\GreetingAspect;
use Go\Yii3\GoAopBridge\Tests\Weaving\WeavingTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use ReflectionMethod;
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
        yield 'opcache' => [OpcacheWarmupCommand::class, 'aop:opcache'];
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

    public function testWarmupBuildsAnotherCacheDirectoryWithItsPreloadScript(): void
    {
        $cacheDir = $this->runtime . '/next-release';
        $tester   = new CommandTester($this->command(WarmupCommand::class));

        $this->assertSame(Command::SUCCESS, $tester->execute(['--cache-dir' => $cacheDir, '--preload' => null]));
        $this->assertFileExists($cacheDir . '/src/Service/GreetingService.php');
        $this->assertFileExists($cacheDir . '/_preload.php');
        // The kernel keeps its own directory
        $this->assertFalse($this->cacheContainsFileFor('GreetingService'));
    }

    public function testWarmupWeavesInThisProcessWhenItDoesNotRunTheEntryScript(): void
    {
        $tester = new CommandTester($this->command(WarmupCommand::class));

        $this->assertSame(Command::SUCCESS, $tester->execute(['--workers' => '2'], ['capture_stderr_separately' => true]));
        $this->assertStringContainsString('weaving in this process', $tester->getErrorOutput());
        $this->assertStringNotContainsString('workers', $tester->getErrorOutput() . $tester->getDisplay());
        $this->assertTrue($this->cacheContainsFileFor('GreetingService'));
    }

    public function testWorkerCommandLineRunsTheEntryScriptAgain(): void
    {
        $command     = $this->command(WarmupCommand::class);
        $entryScript = AspectYii3Kernel::instance()->getEntryScript();
        $this->assertIsString($entryScript);
        // As if this process were started by the entry script, the way the console runs
        $_SERVER['SCRIPT_FILENAME'] = $entryScript;
        $commandLine = new ReflectionMethod($command, 'getWorkerCommandLine');

        $this->assertSame(
            [PHP_BINARY, '-d', 'memory_limit=' . ini_get('memory_limit'), '-d', 'display_errors=stderr', $entryScript, WarmupWorkerCommand::NAME, '--cached-files=7', '--cache-dir=/srv/next'],
            $commandLine->invoke($command, 7, '/srv/next'),
        );
        // No cache directory, no option
        $withoutCacheDir = $commandLine->invoke($command, 7, null);
        $this->assertIsArray($withoutCacheDir);
        $this->assertCount(8, $withoutCacheDir);
    }

    public function testWorkerRejectsCachedFilesThatAreNoPositiveInteger(): void
    {
        $tester = new CommandTester($this->command(WarmupWorkerCommand::class));

        $this->expectException(InvalidConfigurationException::class);
        $tester->execute(['--cached-files' => '0']);
    }

    public function testWarmupWeavesWithFewerCachedFiles(): void
    {
        $tester = new CommandTester($this->command(WarmupCommand::class));

        $this->assertSame(Command::SUCCESS, $tester->execute(['--cached-files' => '1']));
        $this->assertTrue($this->cacheContainsFileFor('GreetingService'));
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
