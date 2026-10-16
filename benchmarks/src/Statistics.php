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
 * Summary of the samples of one measurement
 */
final readonly class Statistics
{
    private function __construct(
        public float $median,
        public float $p95,
        public float $min,
        public float $mean,
        public int $count,
    ) {}

    /**
     * @param list<float> $values
     */
    public static function of(array $values): self
    {
        if ($values === []) {
            throw new InvalidArgumentException('No samples to summarize.');
        }
        sort($values);

        return new self(
            self::percentile($values, 50),
            self::percentile($values, 95),
            $values[0],
            array_sum($values) / count($values),
            count($values),
        );
    }

    /**
     * @return array{median: float, p95: float, min: float, mean: float, count: int}
     */
    public function toArray(): array
    {
        return [
            'median' => round($this->median, 3),
            'p95' => round($this->p95, 3),
            'min' => round($this->min, 3),
            'mean' => round($this->mean, 3),
            'count' => $this->count,
        ];
    }

    /**
     * Linear interpolation between the closest ranks
     *
     * @param non-empty-list<float> $sorted
     */
    private static function percentile(array $sorted, int $percent): float
    {
        $rank  = ($percent / 100) * (count($sorted) - 1);
        $lower = (int) floor($rank);
        $upper = min($lower + 1, count($sorted) - 1);

        return $sorted[$lower] + ($sorted[$upper] - $sorted[$lower]) * ($rank - $lower);
    }
}
