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
 * The standalone PHP server (php -S) serving the front controller, one worker
 */
final class Server
{
    /**
     * @param resource $process
     */
    private function __construct(private $process, private readonly int $port, private readonly string $logFile) {}

    /**
     * @param array<string, string|int> $ini
     * @param array<string, string> $env
     */
    public static function start(string $php, array $ini, string $router, array $env, string $logFile): self
    {
        $port    = self::freePort();
        $command = [$php, ...Process::iniArguments($ini), '-S', '127.0.0.1:' . $port, '-t', dirname($router), $router];
        $process = proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => ['file', $logFile, 'a'], 2 => ['file', $logFile, 'a']], $pipes, dirname($router), $env);
        if (!is_resource($process)) {
            throw new RuntimeException('Can not start ' . implode(' ', $command));
        }
        $server = new self($process, $port, $logFile);
        $server->waitUntilReady();

        return $server;
    }

    /**
     * Sends a request and returns the measurement printed by the front controller
     */
    public function request(): Sample
    {
        $start    = hrtime(true);
        $context  = stream_context_create(['http' => ['timeout' => 60, 'ignore_errors' => true]]);
        $body     = @file_get_contents('http://127.0.0.1:' . $this->port . '/', false, $context);
        $wallTime = (hrtime(true) - $start) / 1e6;
        if (!is_string($body)) {
            throw new RuntimeException('The server did not answer, see ' . $this->logFile);
        }

        return Sample::fromJson($body, $wallTime);
    }

    public function stop(): void
    {
        proc_terminate($this->process);
        proc_close($this->process);
    }

    private function waitUntilReady(): void
    {
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $socket = @fsockopen('127.0.0.1', $this->port, $errorCode, $errorMessage, 0.1);
            if (is_resource($socket)) {
                fclose($socket);

                return;
            }
            usleep(50_000);
        }
        $this->stop();
        throw new RuntimeException('The server did not start, see ' . $this->logFile);
    }

    private static function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        if ($socket === false) {
            throw new RuntimeException('No free port.');
        }
        $name = (string) stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr($name, (int) strrpos($name, ':') + 1);
    }
}
