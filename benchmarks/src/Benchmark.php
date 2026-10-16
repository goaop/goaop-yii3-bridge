<?php
/*
 * Go! AOP framework
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace Go\Yii3\GoAopBridge\Benchmarks;

use Closure;
use UnexpectedValueException;

/**
 * Measures the front controller in every requested mode, interleaving the scenarios so drift affects them alike
 */
final readonly class Benchmark
{
    /**
     * @param Closure(string): void $log
     */
    public function __construct(
        private Options $options,
        private string $rootPath,
        private string $workDirectory,
        private Closure $log,
    ) {}

    public function appDirectory(): string
    {
        return $this->workDirectory . '/app';
    }

    /**
     * Writes the benchmark application, see {@see AppGenerator}
     */
    public function generate(): void
    {
        new AppGenerator($this->appDirectory(), $this->options->services, $this->options->aspects)->generate();
    }

    public function run(): Results
    {
        $this->generate();

        $results = new Results();
        foreach ($this->options->modes as $mode) {
            foreach ($this->options->debugModes as $debug) {
                ($this->log)(sprintf('%s, %s mode', $mode->label(), $debug ? 'debug' : 'production'));
                match ($mode) {
                    Mode::CliCold, Mode::CliWarm => $this->measureCli($results, $mode, $debug),
                    Mode::ServerHot => $this->measureServer($results, $debug),
                };
            }
        }

        return $results;
    }

    /**
     * PHP settings of every mode: production-like, opcache only for the server
     *
     * @return array<string, string|int>
     */
    public function ini(Mode $mode, bool $debug): array
    {
        $ini = ['memory_limit' => '-1', 'zend.assertions' => -1, 'display_errors' => 'stderr', 'log_errors' => 0, 'opcache.enable_cli' => 0];
        if ($mode !== Mode::ServerHot) {
            return $ini;
        }

        return $ini + [
            'opcache.enable' => 1,
            'opcache.jit' => $this->options->jit,
            'opcache.memory_consumption' => 128,
            'opcache.interned_strings_buffer' => 16,
            'opcache.max_accelerated_files' => 20000,
            // Woven files are written right before they are included, opcache must not skip them as "too new"
            'opcache.file_update_protection' => 0,
            // Production deployments do not check the files for changes, development setups do on every request
            'opcache.validate_timestamps' => $debug ? 1 : 0,
            'opcache.revalidate_freq' => 0,
        ];
    }

    /**
     * Executes the front controller in a PHP process of a CLI mode
     *
     * @param bool $resetRuntime Whether to empty the AOP cache first
     */
    public function cliSample(Mode $mode, bool $debug, Scenario $scenario, bool $resetRuntime): Sample
    {
        if ($resetRuntime) {
            $this->resetRuntime($mode, $debug, $scenario);
        }
        $output = Process::run(
            [$this->options->php, ...Process::iniArguments($this->ini($mode, $debug)), $this->frontController()],
            $this->env($mode, $debug, $scenario),
            $this->rootPath,
        );

        return $this->validate(Sample::fromJson($output['stdout'], $output['wallTime']), $debug, $scenario, $output['stderr']);
    }

    private function measureCli(Results $results, Mode $mode, bool $debug): void
    {
        if ($mode === Mode::CliWarm) {
            foreach ($this->options->scenarios as $scenario) {
                $this->cliSample($mode, $debug, $scenario, resetRuntime: true);
            }
        }
        for ($run = 0; $run < $this->options->runs; $run++) {
            foreach ($this->options->scenarios as $scenario) {
                $results->add($mode, $debug, $scenario, $this->cliSample($mode, $debug, $scenario, $mode === Mode::CliCold));
            }
        }
    }

    private function measureServer(Results $results, bool $debug): void
    {
        $servers = [];
        try {
            foreach ($this->options->scenarios as $scenario) {
                // The AOP cache is filled before the server starts, as "aop:warmup" does at deployment (opcache.enable_cli=0)
                $this->cliSample(Mode::ServerHot, $debug, $scenario, resetRuntime: true);
                Filesystem::ensureDirectory($this->workDirectory . '/logs');
                $servers[] = [$scenario, Server::start(
                    $this->options->php,
                    $this->ini(Mode::ServerHot, $debug),
                    $this->frontController(),
                    $this->env(Mode::ServerHot, $debug, $scenario),
                    sprintf('%s/logs/server-%s-%s.log', $this->workDirectory, $debug ? 'debug' : 'prod', $scenario->value),
                )];
            }
            // The first requests fill the opcache
            for ($request = 0; $request < $this->options->warmup; $request++) {
                foreach ($servers as [$scenario, $server]) {
                    $this->validate($server->request(), $debug, $scenario);
                }
            }
            for ($request = 0; $request < $this->options->requests; $request++) {
                foreach ($servers as [$scenario, $server]) {
                    $sample = $this->validate($server->request(), $debug, $scenario);
                    if (!$sample->opcache) {
                        throw new UnexpectedValueException('The server runs without opcache.');
                    }
                    $results->add(Mode::ServerHot, $debug, $scenario, $sample);
                }
            }
        } finally {
            foreach ($servers as [, $server]) {
                $server->stop();
            }
        }
    }

    /**
     * Checks that the request did the expected work: every call made, every advice run in the aspects scenario only
     */
    private function validate(Sample $sample, bool $debug, Scenario $scenario, string $stderr = ''): Sample
    {
        $calls    = $this->options->services * ($this->options->iterations + 1);
        $advices  = $scenario === Scenario::Aspects ? $calls : 0;
        if ($sample->scenario !== $scenario->value || $sample->debug !== $debug || $sample->calls !== $calls
            || $sample->advices !== $advices || trim($stderr) !== '') {
            throw new UnexpectedValueException(sprintf(
                'Unexpected measurement of the %s scenario (%d calls and %d advices expected, %d and %d made). %s',
                $scenario->value,
                $calls,
                $advices,
                $sample->calls,
                $sample->advices,
                $stderr,
            ));
        }

        return $sample;
    }

    /**
     * @return array<string, string>
     */
    private function env(Mode $mode, bool $debug, Scenario $scenario): array
    {
        return [
            'PATH' => (string) getenv('PATH'),
            'BENCH_SCENARIO' => $scenario->value,
            'BENCH_DEBUG' => $debug ? '1' : '0',
            'BENCH_APP' => $this->appDirectory(),
            'BENCH_RUNTIME' => $this->runtime($mode, $debug, $scenario),
            'BENCH_ITERATIONS' => (string) $this->options->iterations,
        ];
    }

    /**
     * Empties the "@runtime" directory of the application, with the AOP cache in it
     */
    private function resetRuntime(Mode $mode, bool $debug, Scenario $scenario): void
    {
        Filesystem::remove($this->runtime($mode, $debug, $scenario));
        Filesystem::ensureDirectory($this->runtime($mode, $debug, $scenario));
    }

    private function runtime(Mode $mode, bool $debug, Scenario $scenario): string
    {
        return sprintf('%s/runtime/%s-%s-%s', $this->workDirectory, $mode->value, $debug ? 'debug' : 'prod', $scenario->value);
    }

    private function frontController(): string
    {
        return dirname(__DIR__) . '/public/index.php';
    }
}
