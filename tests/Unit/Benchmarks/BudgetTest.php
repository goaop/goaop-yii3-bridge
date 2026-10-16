<?php
/*
 * Go! AOP framework
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace Go\Yii3\GoAopBridge\Tests\Unit\Benchmarks;

use Go\Yii3\GoAopBridge\Benchmarks\Budget;
use Go\Yii3\GoAopBridge\Benchmarks\Mode;
use Go\Yii3\GoAopBridge\Benchmarks\Options;
use Go\Yii3\GoAopBridge\Benchmarks\Results;
use Go\Yii3\GoAopBridge\Benchmarks\Sample;
use Go\Yii3\GoAopBridge\Benchmarks\Scenario;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class BudgetTest extends TestCase
{
    public function testAcceptsResultsWithinTheBudgets(): void
    {
        $results = $this->results(baseline: 1.5, empty: 2.0);

        $this->assertSame([], $this->budget('overhead', 1.0)->check($this->options(), $results));
    }

    public function testReportsAnExceededOverhead(): void
    {
        $violations = $this->budget('overhead', 0.25)->check($this->options(), $this->results(baseline: 1.5, empty: 2.0));

        $this->assertSame(
            ['Median overhead (ms) of the empty scenario (server-hot, production mode): 0.50, budget 0.25'],
            $violations,
        );
    }

    public function testReportsAnExceededRelativeOverhead(): void
    {
        $violations = $this->budget('overheadPercent', 25.0)->check($this->options(), $this->results(baseline: 1.5, empty: 2.0));

        $this->assertCount(1, $violations);
        $this->assertStringStartsWith('Relative overhead (%) of the empty scenario', $violations[0]);
    }

    public function testReportsAWarmProductionRequestLoadingTheWeaver(): void
    {
        $violations = new Budget([])->check($this->options(), $this->results(1.5, 2.0, ['weaver' => true]));

        $this->assertSame(['A warm request of the empty scenario (server-hot, production mode) loaded the weaver.'], $violations);
    }

    public function testReportsAProductionBootLoadingAspects(): void
    {
        $violations = new Budget([])->check($this->options(), $this->results(1.5, 2.0, ['aspectClassesAfterBoot' => 1]));

        $this->assertContains('The boot of the empty scenario (server-hot, production mode) loaded aspect classes.', $violations);
    }

    public function testRejectsAnUnknownMetric(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->budget('latency', 1.0);
    }

    private function budget(string $metric, float $max): Budget
    {
        return new Budget([['mode' => 'server-hot', 'debug' => false, 'scenario' => 'empty', 'metric' => $metric, 'max' => $max]]);
    }

    private function options(): Options
    {
        return new Options(modes: [Mode::ServerHot], debugModes: [false], scenarios: [Scenario::Baseline, Scenario::Empty]);
    }

    /**
     * @param array<string, bool|int> $loaded Subsystems loaded by the requests of the empty scenario
     */
    private function results(float $baseline, float $empty, array $loaded = []): Results
    {
        $results = new Results();
        foreach ([[Scenario::Baseline, $baseline, []], [Scenario::Empty, $empty, $loaded]] as [$scenario, $total, $subsystems]) {
            $results->add(Mode::ServerHot, false, $scenario, new Sample(
                scenario: $scenario->value,
                debug: false,
                phases: ['total' => $total] + array_fill_keys(Sample::PHASES, 0.0),
                memoryPeak: 0,
                includedFiles: 0,
                opcache: true,
                loaded: $subsystems + ['phpParser' => false, 'pointcutParser' => false, 'weaver' => false, 'aspectClasses' => 0, 'aspectClassesAfterBoot' => 0],
                calls: 0,
                advices: 0,
                wallTime: $total,
            ));
        }

        return $results;
    }
}
