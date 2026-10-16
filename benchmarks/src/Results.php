<?php
/*
 * Go! AOP framework
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace Go\Yii3\GoAopBridge\Benchmarks;

use LogicException;

/**
 * Samples of every measured combination of mode, debug flag and scenario
 */
final class Results
{
    /**
     * @var array<string, non-empty-list<Sample>>
     */
    private array $samples = [];

    public function add(Mode $mode, bool $debug, Scenario $scenario, Sample $sample): void
    {
        $this->samples[self::key($mode, $debug, $scenario)][] = $sample;
    }

    public function has(Mode $mode, bool $debug, Scenario $scenario): bool
    {
        return isset($this->samples[self::key($mode, $debug, $scenario)]);
    }

    /**
     * @return non-empty-list<Sample>
     */
    public function samples(Mode $mode, bool $debug, Scenario $scenario): array
    {
        return $this->samples[self::key($mode, $debug, $scenario)]
            ?? throw new LogicException(sprintf('No samples for %s.', self::key($mode, $debug, $scenario)));
    }

    /**
     * @param string $phase One of {@see Sample::PHASES}
     */
    public function phase(Mode $mode, bool $debug, Scenario $scenario, string $phase): Statistics
    {
        return Statistics::of(array_map(
            static fn(Sample $sample): float => $sample->phases[$phase] ?? throw new LogicException("Unknown phase {$phase}."),
            $this->samples($mode, $debug, $scenario),
        ));
    }

    /**
     * Median overhead of the scenario over the baseline, in milliseconds, null without baseline samples
     */
    public function overhead(Mode $mode, bool $debug, Scenario $scenario, string $phase = 'total'): ?float
    {
        if (!$this->has($mode, $debug, Scenario::Baseline)) {
            return null;
        }

        return $this->phase($mode, $debug, $scenario, $phase)->median
            - $this->phase($mode, $debug, Scenario::Baseline, $phase)->median;
    }

    private static function key(Mode $mode, bool $debug, Scenario $scenario): string
    {
        return sprintf('%s/%s/%s', $mode->value, $debug ? 'debug' : 'prod', $scenario->value);
    }
}
