<?php
/*
 * Go! AOP framework
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace Go\Yii3\GoAopBridge\Tests;

use Go\Yii3\GoAopBridge\AopBootstrap;
use Go\Yii3\GoAopBridge\Tests\App\Aspect\ConfiguredServiceAspect;
use Go\Yii3\GoAopBridge\Tests\App\Aspect\GreetingAspect;
use Go\Yii3\GoAopBridge\Tests\App\Aspect\PackageAspect;
use Go\Yii3\GoAopBridge\Tests\App\Aspect\ReportAspect;
use Go\Yii3\GoAopBridge\Tests\Support\TestApplication;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AopBootstrapTest extends TestCase
{
    public function testKernelIsInitializedFromTheMergedParams(): void
    {
        $runtime = TestApplication::prepareRuntime();
        $appDir  = TestApplication::rootPath() . '/tests/Support/App';

        $kernel = TestApplication::initKernel();

        $this->assertTrue($kernel->isInitialized());
        $this->assertFalse($kernel->isContainerAttached());
        $options = $kernel->getOptions();
        $this->assertTrue($options['debug']);
        $this->assertSame($appDir, $options['appDir']);
        $this->assertSame($runtime . '/aop', $options['cacheDir']);
        $this->assertSame([$appDir . '/src'], $options['includePaths']);
        $this->assertContains($appDir . '/src/Aspect', $options['excludePaths']);
    }

    public function testAspectsContributedByPackagesAreMerged(): void
    {
        TestApplication::prepareRuntime();

        $aspects = TestApplication::initKernel()->getAspects();

        $this->assertSame(
            [PackageAspect::class, GreetingAspect::class, ReportAspect::class, ConfiguredServiceAspect::class],
            $aspects->toArray(),
        );
    }

    public function testKernelIsInitializedOncePerProcess(): void
    {
        TestApplication::prepareRuntime();

        $this->assertSame(TestApplication::initKernel(), TestApplication::initKernel());
    }

    public function testMissingBridgeParamsAreReported(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "params" config group has no "goaop/goaop-yii3-bridge" parameters');

        AopBootstrap::init(rootPath: TestApplication::rootPath(), configDirectory: 'tests/Fixtures/AppWithoutBridgeParams');
    }
}
