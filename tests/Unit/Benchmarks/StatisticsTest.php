<?php
/*
 * Go! AOP framework
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace Go\Yii3\GoAopBridge\Tests\Unit\Benchmarks;

use Go\Yii3\GoAopBridge\Benchmarks\Statistics;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class StatisticsTest extends TestCase
{
    public function testSummarizesUnsortedSamples(): void
    {
        $statistics = Statistics::of([5.0, 1.0, 4.0, 2.0, 3.0]);

        $this->assertSame(3.0, $statistics->median);
        $this->assertSame(1.0, $statistics->min);
        $this->assertSame(3.0, $statistics->mean);
        $this->assertEqualsWithDelta(4.8, $statistics->p95, 1e-9);
        $this->assertSame(5, $statistics->count);
    }

    public function testInterpolatesTheMedianOfAnEvenNumberOfSamples(): void
    {
        $this->assertSame(2.5, Statistics::of([1.0, 2.0, 3.0, 4.0])->median);
    }

    public function testSingleSample(): void
    {
        $statistics = Statistics::of([7.0]);

        $this->assertSame(7.0, $statistics->median);
        $this->assertSame(7.0, $statistics->p95);
    }

    public function testRequiresSamples(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Statistics::of([]);
    }
}
