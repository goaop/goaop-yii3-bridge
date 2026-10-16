<?php
/*
 * Go! AOP framework
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace Go\Yii3\GoAopBridge\Benchmarks;

use RuntimeException;

/**
 * Runs a command to completion
 */
final class Process
{
    private function __construct() {}

    /**
     * @param list<string> $command
     * @param array<string, string> $env
     *
     * @return array{stdout: string, stderr: string, wallTime: float} Wall time in milliseconds
     */
    public static function run(array $command, array $env, string $cwd): array
    {
        $start   = hrtime(true);
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd, $env);
        if (!is_resource($process)) {
            throw new RuntimeException('Can not start ' . implode(' ', $command));
        }
        $stdout   = (string) stream_get_contents($pipes[1]);
        $stderr   = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        $wallTime = (hrtime(true) - $start) / 1e6;
        if ($exitCode !== 0) {
            throw new RuntimeException(sprintf(
                "%s exited with code %d:\n%s%s",
                implode(' ', $command),
                $exitCode,
                $stdout,
                $stderr,
            ));
        }

        return ['stdout' => $stdout, 'stderr' => $stderr, 'wallTime' => $wallTime];
    }

    /**
     * Converts ini settings to "-d" arguments of the PHP binary
     *
     * @param array<string, string|int> $ini
     *
     * @return list<string>
     */
    public static function iniArguments(array $ini): array
    {
        $arguments = [];
        foreach ($ini as $name => $value) {
            $arguments[] = '-d';
            $arguments[] = $name . '=' . $value;
        }

        return $arguments;
    }
}
