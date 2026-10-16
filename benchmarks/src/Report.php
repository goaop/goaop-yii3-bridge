<?php
/*
 * Go! AOP framework
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace Go\Yii3\GoAopBridge\Benchmarks;

use Composer\InstalledVersions;

/**
 * Renders the results as Markdown tables and as JSON
 */
final readonly class Report
{
    private const array PACKAGES = ['goaop/framework', 'yiisoft/config', 'yiisoft/di', 'yiisoft/yii-runner-console'];

    /**
     * @param array<string, string> $environment
     */
    public function __construct(private Options $options, private Results $results, private array $environment) {}

    /**
     * Describes the machine and the software the results were measured with
     *
     * @return array<string, string>
     */
    public static function environment(Options $options, string $rootPath): array
    {
        $php = Process::run(
            [$options->php, '-r', 'echo PHP_VERSION, " (", PHP_OS_FAMILY, " ", php_uname("m"), ", ", PHP_ZTS ? "ZTS" : "NTS", ")";'],
            ['PATH' => (string) getenv('PATH')],
            $rootPath,
        );
        $cpu = 'unknown';
        if (is_readable('/proc/cpuinfo') && preg_match('/^model name\s*:\s*(.+)$/m', (string) file_get_contents('/proc/cpuinfo'), $match) === 1) {
            $cpu = trim($match[1]);
        }
        $environment = [
            'date' => gmdate('Y-m-d H:i') . ' UTC',
            'php' => trim($php['stdout']),
            'cpu' => sprintf('%s (%d logical cores)', $cpu, self::cores()),
            'application' => sprintf(
                '%d services (every even one array-defined), %d aspects advising them (%d steady calls per service)',
                $options->services,
                $options->aspects,
                $options->iterations,
            ),
            'samples' => sprintf(
                '%d processes per CLI mode, %d requests (after %d warm-up requests) per server, opcache.jit=%s',
                $options->runs,
                $options->requests,
                $options->warmup,
                $options->jit,
            ),
        ];
        foreach (self::PACKAGES as $package) {
            $environment[$package] = InstalledVersions::isInstalled($package)
                ? sprintf('%s (%s)', InstalledVersions::getPrettyVersion($package), substr((string) InstalledVersions::getReference($package), 0, 8))
                : 'not installed';
        }

        return $environment;
    }

    public function markdown(): string
    {
        $lines = ['| | |', '|---|---|'];
        foreach ($this->environment as $name => $value) {
            $lines[] = sprintf('| %s | %s |', $name, $value);
        }
        $lines[] = '';
        $lines[] = 'Medians in milliseconds; "overhead" compares the median total with the one of the baseline (no bridge).';

        foreach ($this->options->modes as $mode) {
            foreach ($this->options->debugModes as $debug) {
                $lines[] = '';
                $lines[] = sprintf('#### %s, %s mode', $mode->label(), $debug ? 'debug' : 'production');
                $lines[] = '';
                $lines[] = '| Scenario | Total | p95 | Overhead | Entry line | Container | Bootstrap | First calls | Steady calls | Memory peak | Files |';
                $lines[] = '|---|--:|--:|--:|--:|--:|--:|--:|--:|--:|--:|';
                foreach ($this->options->scenarios as $scenario) {
                    $lines[] = $this->row($mode, $debug, $scenario);
                }
            }
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $cells = [];
        foreach ($this->options->modes as $mode) {
            foreach ($this->options->debugModes as $debug) {
                foreach ($this->options->scenarios as $scenario) {
                    $samples = $this->results->samples($mode, $debug, $scenario);
                    $last    = $samples[count($samples) - 1];
                    $phases  = [];
                    foreach (Sample::PHASES as $phase) {
                        $phases[$phase] = $this->results->phase($mode, $debug, $scenario, $phase)->toArray();
                    }
                    $overhead = $this->results->overhead($mode, $debug, $scenario);
                    $cells[]  = [
                        'mode' => $mode->value,
                        'debug' => $debug,
                        'scenario' => $scenario->value,
                        'phases' => $phases,
                        'overhead' => $overhead === null ? null : round($overhead, 3),
                        'wallTime' => Statistics::of(array_map(static fn(Sample $s): float => $s->wallTime, $samples))->toArray(),
                        'memoryPeak' => $last->memoryPeak,
                        'includedFiles' => $last->includedFiles,
                        'loaded' => $last->loaded,
                    ];
                }
            }
        }

        return ['environment' => $this->environment, 'results' => $cells];
    }

    private function row(Mode $mode, bool $debug, Scenario $scenario): string
    {
        $median   = fn(string $phase): string => self::ms($this->results->phase($mode, $debug, $scenario, $phase)->median);
        $total    = $this->results->phase($mode, $debug, $scenario, 'total');
        $samples  = $this->results->samples($mode, $debug, $scenario);
        $overhead = $this->results->overhead($mode, $debug, $scenario);
        $baseline = $this->results->has($mode, $debug, Scenario::Baseline)
            ? $this->results->phase($mode, $debug, Scenario::Baseline, 'total')->median
            : null;

        return sprintf(
            '| %s | %s | %s | %s | %s | %s | %s | %s | %s | %.1f MiB | %d |',
            $scenario->value,
            self::ms($total->median),
            self::ms($total->p95),
            $overhead === null || $scenario === Scenario::Baseline || $baseline === null
                ? '–'
                : sprintf('%+.2f (%+.0f%%)', $overhead, $overhead / $baseline * 100),
            $scenario->usesBridge() ? $median('entryLine') : '–',
            $median('container'),
            $median('bootstrap'),
            $median('firstCalls'),
            $median('steadyCalls'),
            $samples[count($samples) - 1]->memoryPeak / 1048576,
            $samples[count($samples) - 1]->includedFiles,
        );
    }

    private static function ms(float $value): string
    {
        return number_format($value, 2);
    }

    private static function cores(): int
    {
        $cpuinfo = is_readable('/proc/cpuinfo') ? (string) file_get_contents('/proc/cpuinfo') : '';

        return max(1, preg_match_all('/^processor\s*:/m', $cpuinfo));
    }
}
