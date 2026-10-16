<?php
/*
 * Go! AOP framework
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace Go\Yii3\GoAopBridge\Benchmarks;

use InvalidArgumentException;

/**
 * Command line options of benchmarks/run.php
 */
final readonly class Options
{
    public const string USAGE = <<<'TXT'
        Usage: php benchmarks/run.php [options]

          --services=N     services of the generated application (default 50)
          --aspects=N      aspects advising them, 1..services (default 10)
          --iterations=N   calls of every service in the steady phase (default 100)
          --runs=N         CLI processes per scenario and mode (default 30)
          --requests=N     measured server requests per scenario (default 500)
          --warmup=N       discarded server requests per scenario (default 50)
          --modes=LIST     cli-cold,cli-warm,server-hot (default all)
          --debug=LIST     0,1: production and/or debug mode (default 0,1)
          --scenarios=LIST baseline,empty,aspects (default all)
          --jit=MODE       opcache.jit of the server: disable (PHP default), tracing, function (default disable)
          --php=PATH       PHP binary (default the running one)
          --output=DIR     results directory (default benchmarks/var/results)
          --assert         exit with code 1 when a budget of benchmarks/budgets.php is exceeded
          --quick          --runs=5 --requests=60 --warmup=10, for smoke runs

        TXT;

    /**
     * @param list<Mode> $modes
     * @param list<bool> $debugModes
     * @param list<Scenario> $scenarios
     */
    public function __construct(
        public int $services = 50,
        public int $aspects = 10,
        public int $iterations = 100,
        public int $runs = 30,
        public int $requests = 500,
        public int $warmup = 50,
        public array $modes = [Mode::CliCold, Mode::CliWarm, Mode::ServerHot],
        public array $debugModes = [false, true],
        public array $scenarios = [Scenario::Baseline, Scenario::Empty, Scenario::Aspects],
        public string $jit = 'disable',
        public string $php = PHP_BINARY,
        public string $output = '',
        public bool $assert = false,
    ) {}

    /**
     * @param list<string> $argv Arguments without the script name
     */
    public static function fromArguments(array $argv, string $defaultOutput): self
    {
        $values = ['output' => $defaultOutput];
        $flags  = [];
        foreach ($argv as $argument) {
            if (preg_match('/^--([a-z]+)(?:=(.*))?$/', $argument, $match) !== 1) {
                throw new InvalidArgumentException('Unknown argument ' . $argument);
            }
            if (isset($match[2])) {
                $values[$match[1]] = $match[2];
            } else {
                $flags[$match[1]] = true;
            }
        }
        $unknown = array_diff(array_keys($values + $flags), [
            'services', 'aspects', 'iterations', 'runs', 'requests', 'warmup', 'modes', 'debug', 'scenarios', 'jit',
            'php', 'output', 'assert', 'quick', 'help',
        ]);
        if ($unknown !== []) {
            throw new InvalidArgumentException('Unknown option --' . implode(', --', $unknown));
        }
        $quick = isset($flags['quick']);

        return new self(
            services: self::int($values, 'services', 50),
            aspects: self::int($values, 'aspects', 10),
            iterations: self::int($values, 'iterations', 100, 0),
            runs: self::int($values, 'runs', $quick ? 5 : 30),
            requests: self::int($values, 'requests', $quick ? 60 : 500),
            warmup: self::int($values, 'warmup', $quick ? 10 : 50),
            modes: self::list($values, 'modes', Mode::tryFrom(...), [Mode::CliCold, Mode::CliWarm, Mode::ServerHot]),
            debugModes: self::list($values, 'debug', static fn(string $v): ?bool => match ($v) {
                '0' => false,
                '1' => true,
                default => null,
            }, [false, true]),
            scenarios: self::list($values, 'scenarios', Scenario::tryFrom(...), Scenario::cases()),
            jit: in_array($values['jit'] ?? 'disable', ['disable', 'tracing', 'function'], true)
                ? ($values['jit'] ?? 'disable')
                : throw new InvalidArgumentException('--jit must be disable, tracing or function.'),
            php: $values['php'] ?? PHP_BINARY,
            output: $values['output'],
            assert: isset($flags['assert']),
        );
    }

    /**
     * @param list<string> $argv
     */
    public static function wantsHelp(array $argv): bool
    {
        return in_array('--help', $argv, true) || in_array('-h', $argv, true);
    }

    /**
     * @param array<string, string> $values
     */
    private static function int(array $values, string $name, int $default, int $min = 1): int
    {
        if (!isset($values[$name])) {
            return $default;
        }
        $value = filter_var($values[$name], FILTER_VALIDATE_INT);
        if (!is_int($value) || $value < $min) {
            throw new InvalidArgumentException(sprintf('--%s must be an integer >= %d.', $name, $min));
        }

        return $value;
    }

    /**
     * @template T
     *
     * @param array<string, string> $values
     * @param callable(string): (T|null) $parse
     * @param list<T> $default
     *
     * @return list<T>
     */
    private static function list(array $values, string $name, callable $parse, array $default): array
    {
        if (!isset($values[$name])) {
            return $default;
        }
        $items = [];
        foreach (explode(',', $values[$name]) as $item) {
            $parsed = $parse(trim($item)) ?? throw new InvalidArgumentException(sprintf('Invalid --%s value "%s".', $name, $item));
            if (!in_array($parsed, $items, true)) {
                $items[] = $parsed;
            }
        }

        return $items;
    }
}
