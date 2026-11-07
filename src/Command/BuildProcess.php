<?php
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace Go\Yii3\GoAopBridge\Command;

use Go\Instrument\ClassLoading\CachePathManager;

/**
 * Marks a console process that builds the AOP cache as such, before the aspect kernel boots
 *
 * With `Features::PREBUILT_CACHE` a class below the include paths that the cache does not know fails the process.
 * The process building the cache is exempted ({@see CachePathManager::markBuildProcess()}) when it is marked before
 * such a class loads: the framework commands mark it before they boot their kernel. In a Yii3 application the kernel
 * starts in the entry line, and the runner loads classes of the application while it builds the container (every
 * class with an array definition in debug mode, the dependencies of bootstrap callbacks), long before the console
 * runs the command. So the entry line finds out which command the console is about to run, from the command line of
 * the process and the command map of yiisoft/yii-console, and resolves it the way the Symfony console does: the
 * exact name or alias, otherwise an unambiguous abbreviation ("aop:warm").
 *
 * The commands building the cache are recognized by their class, so they may be registered under any name.
 *
 * @internal
 */
final class BuildProcess
{
    /**
     * Commands that build the AOP cache
     */
    public const array COMMAND_CLASSES = [WarmupCommand::class, WarmupWorkerCommand::class, DebugWeavingCommand::class];

    /**
     * Options of the console application that take their value from the next argument (yiisoft/yii-console adds
     * "--config"); the options of the Symfony console take none
     */
    private const array OPTIONS_WITH_VALUE = ['--config'];

    /**
     * Static facade, never instantiated
     *
     * @codeCoverageIgnore
     */
    private function __construct() {}

    /**
     * Marks this process as a build process when it runs one of the {@see COMMAND_CLASSES}
     *
     * @param mixed        $consoleParams The "yiisoft/yii-console" parameters, whose "commands" map names to classes
     * @param list<string> $arguments     Command line of the process, starting with the script ($_SERVER['argv'])
     *
     * @return bool Whether the process was marked
     */
    public static function mark(mixed $consoleParams, array $arguments): bool
    {
        $commandMap = is_array($consoleParams) ? $consoleParams['commands'] ?? null : null;
        if (!is_array($commandMap) || !self::isBuildCommand($commandMap, $arguments)) {
            return false;
        }
        CachePathManager::markBuildProcess();

        return true;
    }

    /**
     * Tells whether the command line runs one of the {@see COMMAND_CLASSES}
     *
     * @param array<mixed> $commandMap Name ("name", "name|alias", "|hidden-name") => command class
     * @param list<string> $arguments  Command line of the process, starting with the script
     */
    public static function isBuildCommand(array $commandMap, array $arguments): bool
    {
        $name = self::commandName($arguments);
        if ($name === null) {
            return false;
        }

        // Every name and alias => [command class, primary name, hidden], as yiisoft/yii-console registers them
        $commands = [];
        foreach ($commandMap as $names => $class) {
            if (!is_string($names) || !is_string($class)) {
                continue;
            }
            $hidden  = str_starts_with($names, '|');
            $aliases = array_values(array_filter(explode('|', $names), static fn(string $alias): bool => $alias !== ''));
            foreach ($aliases as $alias) {
                $commands[$alias] = [ltrim($class, '\\'), $aliases[0], $hidden];
            }
        }
        $class = isset($commands[$name]) ? $commands[$name][0] : self::abbreviatedCommand($name, $commands);

        return $class !== null && in_array($class, self::COMMAND_CLASSES, true);
    }

    /**
     * Returns the first argument that is no option, the name of the command, see ArgvInput::getFirstArgument()
     *
     * @param list<string> $arguments
     */
    private static function commandName(array $arguments): ?string
    {
        $tokens = array_slice($arguments, 1);
        for ($i = 0, $count = count($tokens); $i < $count; $i++) {
            $token = $tokens[$i];
            if ($token === '--') {
                return $tokens[$i + 1] ?? null;
            }
            if (str_starts_with($token, '-')) {
                if (in_array($token, self::OPTIONS_WITH_VALUE, true)) {
                    $i++;
                }
                continue;
            }

            return $token;
        }

        return null;
    }

    /**
     * Resolves an abbreviated name like Application::find() of the Symfony console: the commands whose name starts
     * with the abbreviation of every segment ("aop:w" for "aop:warmup"), case-sensitively first; one of them must
     * match it entirely, and they must all be the same command, hidden commands left aside when there are several
     *
     * @param array<string, array{string, string, bool}> $commands Name or alias => [command class, primary name, hidden]
     */
    private static function abbreviatedCommand(string $name, array $commands): ?string
    {
        $expression = implode('[^:]*:', array_map(preg_quote(...), explode(':', $name))) . '[^:]*';
        $matches    = self::matchingCommands('{^' . $expression . '}', $commands);
        if ($matches === []) {
            $matches = self::matchingCommands('{^' . $expression . '}i', $commands);
        }
        if (self::matchingCommands('{^' . $expression . '$}i', $matches) === []) {
            return null;
        }
        $classes = $visible = [];
        foreach ($matches as [$class, $primaryName, $hidden]) {
            $classes[$primaryName] = $class;
            if (!$hidden) {
                $visible[$primaryName] = $class;
            }
        }
        if (count($classes) > 1) {
            $classes = $visible;
        }

        return count($classes) === 1 ? reset($classes) : null;
    }

    /**
     * @param array<string, array{string, string, bool}> $commands
     *
     * @return array<string, array{string, string, bool}>
     */
    private static function matchingCommands(string $pattern, array $commands): array
    {
        return array_filter(
            $commands,
            static fn(string $name): bool => preg_match($pattern, $name) === 1,
            ARRAY_FILTER_USE_KEY,
        );
    }
}
