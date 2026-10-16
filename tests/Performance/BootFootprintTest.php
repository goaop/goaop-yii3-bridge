<?php
/*
 * Go! AOP framework
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace Go\Yii3\GoAopBridge\Tests\Performance;

use Go\Yii3\GoAopBridge\Benchmarks\Benchmark;
use Go\Yii3\GoAopBridge\Benchmarks\Filesystem;
use Go\Yii3\GoAopBridge\Benchmarks\Mode;
use Go\Yii3\GoAopBridge\Benchmarks\Options;
use Go\Yii3\GoAopBridge\Benchmarks\Sample;
use Go\Yii3\GoAopBridge\Benchmarks\Scenario;
use Go\Yii3\GoAopBridge\Tests\Support\TestApplication;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * What the bridge loads while a request of the benchmark application runs (benchmarks/README.md)
 *
 * Timings depend on the machine and are checked by "php benchmarks/run.php --assert"; the work done is deterministic
 * and checked here: a warm production request never reaches the weaver, aspects stay unloaded until one of their
 * advices runs, and the bridge without aspects adds only its own small set of files.
 */
final class BootFootprintTest extends TestCase
{
    /**
     * Files the bridge without aspects may add to a warm production request: the bridge classes and config, the
     * aspect kernel and its class loader (24 when this test was written)
     */
    private const int MAX_EXTRA_FILES = 30;

    private const int ASPECTS = 3;

    private static string $workDirectory;

    private static Benchmark $benchmark;

    public static function setUpBeforeClass(): void
    {
        self::$workDirectory = TestApplication::rootPath() . '/benchmarks/var/tests-' . getmypid();
        self::$benchmark     = new Benchmark(
            new Options(services: 6, aspects: self::ASPECTS, iterations: 2),
            TestApplication::rootPath(),
            self::$workDirectory,
            static function (string $message): void {},
        );
        self::$benchmark->generate();
    }

    public static function tearDownAfterClass(): void
    {
        Filesystem::remove(self::$workDirectory);
    }

    /**
     * @return iterable<string, array{Scenario}>
     */
    public static function bridgeScenarios(): iterable
    {
        yield 'without aspects' => [Scenario::Empty];
        yield 'with aspects' => [Scenario::Aspects];
    }

    #[DataProvider('bridgeScenarios')]
    public function testWarmProductionRequestNeedsNeitherTheWeaverNorAParser(Scenario $scenario): void
    {
        $sample = $this->warmSample($scenario, debug: false);

        $this->assertTrue($sample->loaded['kernel'] ?? null);
        $this->assertFalse($sample->loaded['weaver'] ?? null);
        $this->assertFalse($sample->loaded['phpParser'] ?? null);
        $this->assertFalse($sample->loaded['pointcutParser'] ?? null);
    }

    public function testProductionBootLoadsNoAspectUntilAnAdviceRuns(): void
    {
        $sample = $this->warmSample(Scenario::Aspects, debug: false);

        $this->assertSame(0, $sample->loaded['aspectClassesAfterBoot'] ?? null);
        $this->assertSame(self::ASPECTS, $sample->loaded['aspectClasses'] ?? null);
        $this->assertSame($sample->calls, $sample->advices, 'Every call of a service runs its advice');
    }

    public function testDebugBootChecksEveryAspect(): void
    {
        $sample = $this->warmSample(Scenario::Aspects, debug: true);

        $this->assertSame(self::ASPECTS, $sample->loaded['aspectClassesAfterBoot'] ?? null);
    }

    public function testColdRequestWeavesEveryAdvisedService(): void
    {
        $sample = self::$benchmark->cliSample(Mode::CliCold, false, Scenario::Aspects, resetRuntime: true);

        $this->assertTrue($sample->loaded['weaver'] ?? null);
        $this->assertSame(0, $sample->loaded['aspectClassesAfterBoot'] ?? null);
        $this->assertSame($sample->calls, $sample->advices);
    }

    public function testBridgeWithoutAspectsAddsOnlyItsOwnFiles(): void
    {
        $baseline = $this->warmSample(Scenario::Baseline, debug: false);
        $empty    = $this->warmSample(Scenario::Empty, debug: false);

        $this->assertSame(0, $empty->loaded['aspectClasses'] ?? null);
        $this->assertLessThanOrEqual(
            self::MAX_EXTRA_FILES,
            $empty->includedFiles - $baseline->includedFiles,
            sprintf('The bridge without aspects included %d files, the baseline %d.', $empty->includedFiles, $baseline->includedFiles),
        );
    }

    /**
     * Fills the AOP cache with a first request, then measures a second one
     */
    private function warmSample(Scenario $scenario, bool $debug): Sample
    {
        self::$benchmark->cliSample(Mode::CliWarm, $debug, $scenario, resetRuntime: true);

        return self::$benchmark->cliSample(Mode::CliWarm, $debug, $scenario, resetRuntime: false);
    }
}
