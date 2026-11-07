<?php
/*
 * Go! AOP framework
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace Go\Yii3\GoAopBridge\Tests\Kernel;

use Go\Aop\Features;
use Go\Core\AspectKernel;
use Go\Instrument\ClassLoading\CachePathManager;
use Go\Yii3\GoAopBridge\Tests\Support\TestApplication;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * A console process running a command that builds the prebuilt cache is marked as a build process before the kernel
 * boots, by the entry line and by the bootstrap fallback (#808)
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class BuildProcessMarkTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool, bool}>
     */
    public static function processes(): iterable
    {
        yield 'warm-up, entry line' => ['aop:warmup', true, false];
        yield 'warm-up, bootstrap fallback' => ['aop:warmup', false, false];
        yield 'application command, entry line' => ['app:greet', true, true];
        yield 'application command, bootstrap fallback' => ['app:greet', false, true];
    }

    #[DataProvider('processes')]
    public function testMissesFailOutsideOfBuildProcesses(string $command, bool $entryLine, bool $failsOnMiss): void
    {
        TestApplication::prepareRuntime();
        putenv('GOAOP_TEST_FEATURES=' . Features::PREBUILT_CACHE);
        $_SERVER['argv'] = ['yii', $command];

        // The runner in production mode: it does not load the classes of array definitions, which miss the cache
        if ($entryLine) {
            TestApplication::initKernel(debug: false);
        }
        TestApplication::container(debug: false);

        $cachePathManager = AspectKernel::getInstance()->getContainer()->getService(CachePathManager::class);
        $this->assertTrue($cachePathManager->servesPrebuiltCache);
        $this->assertSame($failsOnMiss, $cachePathManager->failsOnMiss);
    }
}
