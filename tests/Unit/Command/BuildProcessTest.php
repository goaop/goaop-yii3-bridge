<?php
/*
 * Go! AOP framework
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace Go\Yii3\GoAopBridge\Tests\Unit\Command;

use Go\Instrument\ClassLoading\CachePathManager;
use Go\Yii3\GoAopBridge\Command\BuildProcess;
use Go\Yii3\GoAopBridge\Command\DebugAspectCommand;
use Go\Yii3\GoAopBridge\Command\DebugWeavingCommand;
use Go\Yii3\GoAopBridge\Command\WarmupCommand;
use Go\Yii3\GoAopBridge\Command\WarmupWorkerCommand;
use Go\Yii3\GoAopBridge\Tests\App\Command\GreetCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Finds out from the command line whether the console process builds the AOP cache, as the Symfony console resolves it
 */
final class BuildProcessTest extends TestCase
{
    private const array COMMANDS = [
        'aop:warmup|deploy:aop' => WarmupCommand::class,
        'aop:debug:aspect' => DebugAspectCommand::class,
        'aop:debug:weaving' => '\\' . DebugWeavingCommand::class,
        '|aop:warmup-worker' => WarmupWorkerCommand::class,
        '|aop:hidden-greet' => GreetCommand::class,
        'app:greet' => GreetCommand::class,
        'app:warmup' => GreetCommand::class,
    ];

    /**
     * @return iterable<string, array{list<string>, bool}>
     */
    public static function commandLines(): iterable
    {
        yield 'warm-up' => [['yii', 'aop:warmup'], true];
        yield 'warm-up with options' => [['yii', 'aop:warmup', '--workers=4', '--preload'], true];
        yield 'alias' => [['yii', 'deploy:aop'], true];
        yield 'hidden worker' => [['yii', 'aop:warmup-worker', '--cached-files=10'], true];
        yield 'abbreviation of a hidden command' => [['yii', 'aop:warmup-w'], true];
        yield 'abbreviation of a hidden application command' => [['yii', 'aop:h'], false];
        yield 'debug weaving' => [['yii', 'aop:debug:weaving', '-v'], true];
        yield 'global options first' => [['yii', '-v', '--ansi', '--config=web', 'aop:warmup'], true];
        yield 'option value as next argument' => [['yii', '--config', 'aop:debug:aspect', 'aop:warmup'], true];
        yield 'after the end of options' => [['yii', '--', 'aop:warmup'], true];
        yield 'abbreviation' => [['yii', 'aop:warm'], true];
        yield 'abbreviation of every segment' => [['yii', 'a:d:w'], true];
        yield 'case-insensitive abbreviation' => [['yii', 'AOP:W'], true];
        yield 'other command' => [['yii', 'aop:debug:aspect'], false];
        yield 'application command' => [['yii', 'app:greet', 'aop:warmup'], false];
        yield 'help of the command' => [['yii', 'help', 'aop:warmup'], false];
        yield 'ambiguous abbreviation' => [['yii', 'a:w'], false];
        yield 'abbreviation of a namespace only' => [['yii', 'aop:debug'], false];
        yield 'unknown command' => [['yii', 'aop:warmup-all'], false];
        yield 'no command' => [['yii'], false];
        yield 'options only' => [['yii', '--ansi', '-v'], false];
    }

    /**
     * @param list<string> $arguments
     */
    #[DataProvider('commandLines')]
    public function testCommandLineIsResolvedLikeTheConsole(array $arguments, bool $isBuildCommand): void
    {
        $this->assertSame($isBuildCommand, BuildProcess::isBuildCommand(self::COMMANDS, $arguments));
    }

    public function testCommandsAreRecognizedByTheirClassUnderAnyName(): void
    {
        $this->assertTrue(BuildProcess::isBuildCommand(['cache:aop' => WarmupCommand::class], ['yii', 'cache:aop']));
        $this->assertFalse(BuildProcess::isBuildCommand(['aop:warmup' => GreetCommand::class], ['yii', 'aop:warmup']));
    }

    public function testProcessRunningABuildCommandIsMarked(): void
    {
        try {
            $this->assertTrue(BuildProcess::mark(['commands' => self::COMMANDS], ['yii', 'aop:warmup']));
        } finally {
            CachePathManager::markBuildProcess(false);
        }
    }

    public function testProcessIsNotMarkedWithoutTheCommandsOfTheConsole(): void
    {
        $this->assertFalse(BuildProcess::mark(null, ['yii', 'aop:warmup']));
        $this->assertFalse(BuildProcess::mark(['name' => 'Yii Console'], ['yii', 'aop:warmup']));
        $this->assertFalse(BuildProcess::mark(['commands' => self::COMMANDS], ['yii', 'app:greet']));
    }
}
