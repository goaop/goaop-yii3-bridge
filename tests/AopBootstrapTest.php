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
use Yiisoft\Config\ConfigInterface;

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

    public function testAspectsOfParamsAndTagsAreMerged(): void
    {
        TestApplication::prepareRuntime();

        $aspects = TestApplication::initKernel()->getAspects();

        $this->assertSame(
            // The params of the package and of the application, then the tag map and the tagged definitions
            [PackageAspect::class, GreetingAspect::class, ConfiguredServiceAspect::class, ReportAspect::class],
            $aspects->toArray(),
        );
    }

    public function testKernelIsInitializedOncePerProcess(): void
    {
        TestApplication::prepareRuntime();

        $this->assertSame(TestApplication::initKernel(), TestApplication::initKernel());
    }

    public function testDefinitionsAreReadWithTheParametersOfTheRunner(): void
    {
        TestApplication::prepareRuntime();

        // The common "di" config reads a parameter of "params-console": with the config of the console runner, the
        // entry line evaluates it like the runner does
        $config = TestApplication::runner()->getConfig();
        $kernel = AopBootstrap::init($config, TestApplication::rootPath());

        $this->assertContains(ReportAspect::class, $kernel->getAspects()->toArray());
        $this->assertSame(['configuredPrefix' => 'configured'], $config->get('params-console')['app'] ?? null);
    }

    public function testMissingConfigGroupIsReported(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The config has no "goaop-kernel" group, is the package config merged (composer yii-config-rebuild)?');

        TestApplication::initKernel(configDirectory: 'tests/Fixtures/AppWithoutBridgeParams');
    }

    public function testMissingBridgeParamsAreReported(): void
    {
        $config = new readonly class implements ConfigInterface {
            /**
             * @return array<mixed>
             */
            public function get(string $group): array
            {
                return ['goaop/goaop-yii3-bridge' => null, 'yiisoft/aliases' => null];
            }

            public function has(string $group): bool
            {
                return $group === AopBootstrap::CONFIG_GROUP;
            }
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The config parameters have no "goaop/goaop-yii3-bridge" section');

        AopBootstrap::init($config, TestApplication::rootPath());
    }
}
