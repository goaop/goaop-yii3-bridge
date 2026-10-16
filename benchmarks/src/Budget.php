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
 * Checks the results against the budgets of benchmarks/budgets.php
 *
 * Besides the timing budgets, the structural rules always apply: a warm production request never loads the weaver
 * or a parser, a production boot loads no aspect class (aspects are resolved on the first call of an advice), and
 * the bridge without aspects never loads an aspect class.
 */
final readonly class Budget
{
    public const array METRICS = ['total', 'overhead', 'overheadPercent', 'entryLine', 'bootstrap', 'firstCalls'];

    /**
     * @param list<array{mode: string, debug: bool, scenario: string, metric: string, max: float}> $budgets
     */
    public function __construct(private array $budgets)
    {
        foreach ($budgets as $budget) {
            if (Mode::tryFrom($budget['mode']) === null || Scenario::tryFrom($budget['scenario']) === null
                || !in_array($budget['metric'], self::METRICS, true)) {
                throw new InvalidArgumentException('Invalid budget ' . json_encode($budget));
            }
        }
    }

    /**
     * @return list<string> Violations, empty when every budget is met
     */
    public function check(Options $options, Results $results): array
    {
        $violations = [];
        foreach ($options->modes as $mode) {
            foreach ($options->debugModes as $debug) {
                foreach ($options->scenarios as $scenario) {
                    array_push($violations, ...$this->structural($results, $mode, $debug, $scenario));
                }
            }
        }
        foreach ($this->budgets as $budget) {
            $mode     = Mode::from($budget['mode']);
            $scenario = Scenario::from($budget['scenario']);
            if (!$results->has($mode, $budget['debug'], $scenario)) {
                continue;
            }
            $value = $this->metric($results, $mode, $budget['debug'], $scenario, $budget['metric']);
            if ($value !== null && $value > $budget['max']) {
                $violations[] = sprintf(
                    '%s of the %s scenario (%s, %s mode): %.2f, budget %.2f',
                    $budget['metric'] === 'overheadPercent' ? 'Relative overhead (%)' : 'Median ' . $budget['metric'] . ' (ms)',
                    $scenario->value,
                    $mode->value,
                    $budget['debug'] ? 'debug' : 'production',
                    $value,
                    $budget['max'],
                );
            }
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    private function structural(Results $results, Mode $mode, bool $debug, Scenario $scenario): array
    {
        if (!$results->has($mode, $debug, $scenario)) {
            return [];
        }
        $violations = [];
        $where      = sprintf('the %s scenario (%s, %s mode)', $scenario->value, $mode->value, $debug ? 'debug' : 'production');
        foreach ($results->samples($mode, $debug, $scenario) as $sample) {
            if (!$debug && ($sample->loaded['aspectClassesAfterBoot'] ?? 0) !== 0) {
                $violations['boot'] = sprintf('The boot of %s loaded aspect classes.', $where);
            }
            if (!$debug && $mode !== Mode::CliCold) {
                foreach (['phpParser', 'pointcutParser', 'weaver'] as $subsystem) {
                    if (($sample->loaded[$subsystem] ?? false) === true) {
                        $violations[$subsystem] = sprintf('A warm request of %s loaded the %s.', $where, $subsystem);
                    }
                }
            }
            if ($scenario === Scenario::Empty && ($sample->loaded['aspectClasses'] ?? 0) !== 0) {
                $violations['aspects'] = sprintf('A request of %s loaded aspect classes.', $where);
            }
        }

        return array_values($violations);
    }

    private function metric(Results $results, Mode $mode, bool $debug, Scenario $scenario, string $metric): ?float
    {
        return match ($metric) {
            'overhead' => $results->overhead($mode, $debug, $scenario),
            'overheadPercent' => ($overhead = $results->overhead($mode, $debug, $scenario)) === null
                ? null
                : $overhead / $results->phase($mode, $debug, Scenario::Baseline, 'total')->median * 100,
            default => $results->phase($mode, $debug, $scenario, $metric)->median,
        };
    }
}
