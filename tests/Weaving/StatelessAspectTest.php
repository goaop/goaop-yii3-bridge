<?php
/*
 * Go! AOP framework
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace Go\Yii3\GoAopBridge\Tests\Weaving;

use Go\Yii3\GoAopBridge\Tests\App\Service\ConfiguredService;
use Go\Yii3\GoAopBridge\Tests\Fixtures\StatelessAspect;
use Go\Yii3\GoAopBridge\Tests\Support\TestApplication;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Throwable;

/**
 * An aspect without instance properties can not be lazy: its factory runs when the weaver reads it. It is the
 * instance of the Yii container all the same, or an error when the container does not exist yet.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class StatelessAspectTest extends WeavingTestCase
{
    private const string CONFIG_DIRECTORY = 'tests/Fixtures/AppWithStatelessAspect';

    public function testAspectIsCreatedByItsContainerDefinition(): void
    {
        // Without validation the service is loaded, and woven, after the container was attached
        TestApplication::initKernel(debug: false, configDirectory: self::CONFIG_DIRECTORY);
        $container = TestApplication::container(debug: false, configDirectory: self::CONFIG_DIRECTORY);

        $service = $container->get(ConfiguredService::class);
        $this->assertInstanceOf(ConfiguredService::class, $service);
        $this->assertSame('stateless configured', $service->describe());

        $this->assertSame(1, StatelessAspect::$factoryCalls);
        $this->assertSame($container->get(StatelessAspect::class), StatelessAspect::$advisedBy);
    }

    public function testAspectNeededWhileTheContainerIsBuiltIsReported(): void
    {
        // The validating container loads the service while it is built: the weaver needs the aspect before the
        // container exists, it is not created behind the back of its definition
        TestApplication::initKernel(debug: true, configDirectory: self::CONFIG_DIRECTORY);

        try {
            TestApplication::container(debug: true, configDirectory: self::CONFIG_DIRECTORY);
            $this->fail('The aspect must not be created without the container');
        } catch (Throwable $exception) {
            $this->assertStringContainsString(
                'Aspect "' . StatelessAspect::class . '" is needed by the weaver before the Yii container is attached',
                $exception->getMessage(),
            );
        }
        $this->assertSame(0, StatelessAspect::$factoryCalls);
    }
}
