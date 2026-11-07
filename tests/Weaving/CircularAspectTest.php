<?php
/*
 * Go! AOP framework
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace Go\Yii3\GoAopBridge\Tests\Weaving;

use Go\Aop\Exception\AspectCircularReferenceException;
use Go\Yii3\GoAopBridge\Tests\App\Service\ConfiguredService;
use Go\Yii3\GoAopBridge\Tests\Fixtures\CircularAspect;
use Go\Yii3\GoAopBridge\Tests\Support\TestApplication;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * An aspect whose creation by the Yii container runs one of its own advices is reported by the framework (#783),
 * through the Yii container, with the path from the aspect to its advice
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class CircularAspectTest extends WeavingTestCase
{
    private const string CONFIG_DIRECTORY = 'tests/Fixtures/AppWithCircularAspect';

    public function testAdviceRunningWhileItsAspectIsCreatedIsReported(): void
    {
        TestApplication::initKernel(configDirectory: self::CONFIG_DIRECTORY);
        $service = TestApplication::container(configDirectory: self::CONFIG_DIRECTORY)->get(ConfiguredService::class);
        $this->assertInstanceOf(ConfiguredService::class, $service);

        try {
            $service->describe();
            $this->fail('The advice must not run on an aspect being created');
        } catch (AspectCircularReferenceException $exception) {
            $this->assertSame(CircularAspect::class, $exception->aspectClass);
            $this->assertStringContainsString('Circular reference detected for the aspect "' . CircularAspect::class . '"', $exception->getMessage());
            $this->assertStringContainsString('Inject the dependency lazily', $exception->getMessage());
        }
    }
}
