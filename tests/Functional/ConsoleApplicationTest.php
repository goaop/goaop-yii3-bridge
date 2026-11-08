<?php
/*
 * Go! AOP framework
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace Go\Yii3\GoAopBridge\Tests\Functional;

use Go\Aop\Features;
use Go\Yii3\GoAopBridge\Tests\App\Service\ConfiguredService;
use Go\Yii3\GoAopBridge\Tests\Support\TestApplication;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Runs the console entry script of the fixture application (tests/Support/App/yii) in its own PHP process
 */
final class ConsoleApplicationTest extends TestCase
{
    private string $runtime;

    protected function setUp(): void
    {
        $this->runtime = sys_get_temp_dir() . '/goaop-yii3-bridge-tests/functional-' . bin2hex(random_bytes(4));
        mkdir($this->runtime, 0777, true);
    }

    public function testApplicationCommandRunsWovenService(): void
    {
        [$exitCode, $output] = $this->yii(['app:greet', 'hello']);

        $this->assertSame(0, $exitCode, $output);
        $this->assertSame('HELLO!', trim($output));
    }

    public function testBootstrapFallbackWeavesWithoutTheEntryLine(): void
    {
        [$exitCode, $output] = $this->yii(['app:greet', 'fallback'], entryLine: false);

        $this->assertSame(0, $exitCode, $output);
        $this->assertSame('FALLBACK!', trim($output));
    }

    public function testCommandsAreListedInTheAopNamespace(): void
    {
        [$exitCode, $output] = $this->yii(['list', 'aop']);

        $this->assertSame(0, $exitCode, $output);
        foreach (['aop:warmup', 'aop:opcache', 'aop:debug:aspect', 'aop:debug:advisor', 'aop:debug:weaving'] as $command) {
            $this->assertStringContainsString($command, $output);
        }
        $this->assertStringNotContainsString('aop:warmup-worker', $output);
    }

    public function testWarmupPreparesTheCacheForProduction(): void
    {
        [$exitCode, $output] = $this->yii(['aop:warmup']);

        $this->assertSame(0, $exitCode, $output);
        $this->assertStringContainsString('Total processed', $output);
        $this->assertFileExists($this->runtime . '/aop/src/Service/GreetingService.php');
        $this->assertFileExists($this->runtime . '/aop/src/Service/GreetingServiceOriginalTrait.php');
    }

    public function testWarmupWeavesInWorkerProcessesOfTheEntryScript(): void
    {
        [$exitCode, $output] = $this->yii(['aop:warmup', '--workers=2']);

        $this->assertSame(0, $exitCode, $output);
        $this->assertStringContainsString('Weaving in 2 parallel worker processes.', $output);
        $this->assertStringContainsString('0 errors', $output);
        $this->assertFileExists($this->runtime . '/aop/src/Service/GreetingService.php');
        $this->assertFileExists($this->runtime . '/aop/src/Service/ReportRepository.php');

        [$exitCode, $output] = $this->yii(['app:greet', 'parallel']);
        $this->assertSame(0, $exitCode, $output);
        $this->assertSame('PARALLEL!', trim($output));
    }

    public function testWarmupWorkersBuildAnotherCacheDirectory(): void
    {
        [$exitCode, $output] = $this->yii(['aop:warmup', '--workers=2', '--cached-files=2', '--cache-dir=' . $this->runtime . '/next']);

        $this->assertSame(0, $exitCode, $output);
        $this->assertStringContainsString('with 2 workers', $output);
        $this->assertFileExists($this->runtime . '/next/src/Service/GreetingService.php');
        $this->assertFileExists($this->runtime . '/next/src/Service/ReportRepository.php');
        $this->assertFileDoesNotExist($this->runtime . '/aop/src/Service/ReportRepository.php');
    }

    public function testWarmupOfTheBootstrapFallbackWeavesInItsOwnProcess(): void
    {
        [$exitCode, $output] = $this->yii(['aop:warmup', '--workers=2'], entryLine: false);

        $this->assertSame(0, $exitCode, $output);
        $this->assertStringContainsString('weaving in this process', $output);
        $this->assertStringNotContainsString('parallel worker processes', $output);
        $this->assertFileExists($this->runtime . '/aop/src/Service/GreetingService.php');
    }

    public function testOpcacheCommandNeedsTheFileCacheSettings(): void
    {
        [$exitCode, $output] = $this->yii(['aop:opcache']);

        $this->assertSame(1, $exitCode, $output);
        $this->assertStringContainsString('Can not prime the opcache file cache', $output);
        $this->assertStringContainsString('-d opcache.file_cache_only=1', $output);
    }

    public function testOpcacheCommandPrimesTheFileCacheAfterTheWarmup(): void
    {
        if (!extension_loaded('Zend OPcache')) {
            $this->markTestSkipped('The opcache extension is not loaded.');
        }
        $fileCache = $this->runtime . '/opcache';
        mkdir($fileCache);
        [$exitCode, $output] = $this->yii(['aop:warmup']);
        $this->assertSame(0, $exitCode, $output);

        [$exitCode, $output] = $this->yii(
            ['aop:opcache', '--file-cache=' . $fileCache],
            phpOptions: ['opcache.enable_cli=1', 'opcache.file_cache_only=1', 'opcache.file_cache=' . $fileCache],
        );

        $this->assertSame(0, $exitCode, $output);
        $this->assertMatchesRegularExpression('/Compiled \d+ files\./', $output);
        $compiledFiles = $this->compiledFiles($fileCache);
        $rootPath      = (string) realpath(TestApplication::rootPath());
        // Without --entry: the entry script, the merge plan and the config files of the application and the packages
        foreach ([
            'tests/Support/App/yii',
            'tests/Support/App/config/.merge-plan.php',
            'tests/Support/App/config/params.php',
            'tests/Support/App/config/di.php',
            'tests/Support/App/config/params-console.php',
            'config/params.php',
            'config/di.php',
            'config/params-console.php',
            'vendor/yiisoft/yii-console/config/params-console.php',
        ] as $file) {
            $this->assertContains($rootPath . '/' . $file, $compiledFiles, 'The config file ' . $file);
        }
    }

    /**
     * Returns the source files the file cache holds a compiled script of
     *
     * @return list<string>
     */
    private function compiledFiles(string $fileCache): array
    {
        $files    = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($fileCache, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $path = $file instanceof \SplFileInfo ? $file->getPathname() : '';
            if (str_ends_with($path, '.bin')) {
                // <file cache>/<system id>/<absolute path of the source>.bin
                $relativePath = substr($path, strlen($fileCache) + 1, -strlen('.bin'));
                $files[]      = substr($relativePath, (int) strpos($relativePath, '/'));
            }
        }

        return $files;
    }

    public function testPrebuiltCacheMissFailsTheApplication(): void
    {
        // The container reflects ConfiguredService (an array definition) while the runner builds it in debug mode
        [$exitCode, $output] = $this->yii(['app:greet', 'hello'], features: Features::PREBUILT_CACHE);

        $this->assertNotSame(0, $exitCode, $output);
        $this->assertStringContainsString(
            sprintf('Class %s (%s) is missing from the prebuilt AOP cache', ConfiguredService::class, TestApplication::rootPath() . '/tests/Support/App/src/Service/ConfiguredService.php'),
            $output,
        );
    }

    /**
     * @return iterable<string, array{list<string>, bool}>
     */
    public static function buildCommands(): iterable
    {
        yield 'warm-up' => [['aop:warmup'], true];
        yield 'abbreviated warm-up' => [['--ansi', 'aop:warm'], true];
        yield 'warm-up in workers' => [['aop:warmup', '--workers=2'], true];
        yield 'warm-up started by the bootstrap callback' => [['aop:warmup'], false];
        yield 'debug weaving' => [['aop:debug:weaving'], true];
    }

    /**
     * The process building a prebuilt cache loads classes the cache misses before the command runs (#808)
     *
     * @param list<string> $arguments
     */
    #[DataProvider('buildCommands')]
    public function testBuildCommandBuildsAPrebuiltCacheTheApplicationLoadsBeforeIt(array $arguments, bool $entryLine): void
    {
        [$exitCode, $output] = $this->yii($arguments, $entryLine, features: Features::PREBUILT_CACHE);
        $this->assertSame(0, $exitCode, $output);
        $this->assertFileExists($this->runtime . '/aop/src/Service/ConfiguredService.php');

        [$exitCode, $output] = $this->yii(['app:greet', 'prebuilt'], $entryLine, features: Features::PREBUILT_CACHE);
        $this->assertSame(0, $exitCode, $output);
        $this->assertSame('PREBUILT!', trim($output));
    }

    /**
     * @param list<string> $arguments
     * @param list<string> $phpOptions ini settings of the process
     *
     * @return array{int, string}
     */
    private function yii(array $arguments, bool $entryLine = true, int $features = 0, array $phpOptions = []): array
    {
        $command = [PHP_BINARY];
        foreach ($phpOptions as $option) {
            $command[] = '-d';
            $command[] = $option;
        }
        $command = [...$command, TestApplication::rootPath() . '/tests/Support/App/yii', ...$arguments];
        $env     = [
            'GOAOP_TEST_RUNTIME'    => $this->runtime,
            'GOAOP_TEST_ENTRY_LINE' => $entryLine ? '1' : '0',
            'GOAOP_TEST_FEATURES'   => (string) $features,
            'PATH'                  => (string) getenv('PATH'),
        ];
        // Lets the ini of the test run reach the process, e.g. the JIT disabled for PHP 8.5.11 (AGENTS.md)
        $iniScanDir = getenv('PHP_INI_SCAN_DIR');
        if (is_string($iniScanDir)) {
            $env['PHP_INI_SCAN_DIR'] = $iniScanDir;
        }

        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
        $this->assertIsResource($process);
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $output];
    }
}
