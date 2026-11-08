<?php
/*
 * Go! AOP framework
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace Go\Yii3\GoAopBridge\Tests\Command;

use Go\Console\Command\DebugAdvisorCommand;
use Go\Console\Command\DebugAspectCommand;
use Go\Console\Command\DebugWeavingCommand;
use Go\Yii3\GoAopBridge\Command\OpcacheWarmupCommand;
use Go\Yii3\GoAopBridge\Command\WarmupCommand;
use Go\Yii3\GoAopBridge\Command\WarmupWorkerCommand;
use Go\Yii3\GoAopBridge\Kernel\AspectYii3Kernel;
use Go\Aop\Exception\InvalidConfigurationException;
use Go\Yii3\GoAopBridge\Tests\App\Aspect\GreetingAspect;
use Go\Yii3\GoAopBridge\Tests\Support\TestApplication;
use Go\Yii3\GoAopBridge\Tests\Weaving\WeavingTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use ReflectionMethod;
use Yiisoft\Yii\Console\CommandLoader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LazyCommand;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The commands run on the aspect kernel the entry line initialized
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class CommandTest extends WeavingTestCase
{
    /**
     * @return iterable<string, array{string, class-string<Command>}>
     */
    public static function commands(): iterable
    {
        yield 'warmup' => ['aop:warmup', WarmupCommand::class];
        yield 'warmup worker' => ['aop:warmup-worker', WarmupWorkerCommand::class];
        yield 'opcache' => ['aop:opcache', OpcacheWarmupCommand::class];
        yield 'debug aspect' => ['aop:debug:aspect', DebugAspectCommand::class];
        yield 'debug advisor' => ['aop:debug:advisor', DebugAdvisorCommand::class];
        yield 'debug weaving' => ['aop:debug:weaving', DebugWeavingCommand::class];
    }

    /**
     * The framework commands are registered as they are: the console names them from the command map
     *
     * @param class-string<Command> $commandClass
     */
    #[DataProvider('commands')]
    public function testCommandIsRegisteredInTheAopNamespace(string $name, string $commandClass): void
    {
        /** @var array{'yiisoft/yii-console': array{commands: array<string, class-string<Command>>}} $params */
        $params = require dirname(__DIR__, 2) . '/config/params-console.php';
        $loader = new CommandLoader($this->startApplication(), $params['yiisoft/yii-console']['commands']);

        $command = $loader->get($name);
        $this->assertSame($name, $command->getName());
        $this->assertNotSame('', $command->getDescription());
        $this->assertInstanceOf($commandClass, $command instanceof LazyCommand ? $command->getCommand() : $command);
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

    public function testOpcacheCompilesTheConfigurationAndTheEntryScript(): void
    {
        $command = $this->command(OpcacheWarmupCommand::class);
        $appDir  = (string) realpath(TestApplication::rootPath() . '/tests/Support/App');

        $files = [];
        foreach ((array) (new ReflectionMethod($command, 'applicationFiles'))->invoke($command) as $file) {
            $this->assertIsString($file);
            $files[] = (string) realpath($file);
        }

        // The entry script of the application ("@root/yii"), the merge plan the runner read and the files it lists
        $this->assertContains($appDir . '/yii', $files);
        $this->assertContains($appDir . '/config/.merge-plan.php', $files);
        $this->assertContains($appDir . '/config/params.php', $files);
        $this->assertContains((string) realpath(dirname(__DIR__, 2) . '/config/kernel.php'), $files, 'A file of the vendor override layer');
    }

    public function testOpcacheLeavesAnEntryThatDoesNotResolveToTheFrameworkCommand(): void
    {
        $tester = new CommandTester($this->command(OpcacheWarmupCommand::class));

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('/no/such/entry.php');
        $tester->execute(['--entry' => ['/no/such/entry.php']]);
    }

    public function testOpcacheNeedsTheFileCacheSettingsWithTheFilesOfTheApplication(): void
    {
        $tester = new CommandTester($this->command(OpcacheWarmupCommand::class));

        // An entry that is a file of the application too is passed once; without the file cache settings the
        // framework command stops before compiling
        $exitCode = $tester->execute(['--entry' => [TestApplication::rootPath() . '/tests/Support/App/yii']]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Can not prime the opcache file cache', $tester->getDisplay());
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
