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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * An aspect without instance properties can not be lazy, but the weaver never creates an aspect: it is the instance
 * of the Yii container in both modes, also when the validating container weaves classes while it is built.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class StatelessAspectTest extends WeavingTestCase
{
    private const string CONFIG_DIRECTORY = 'tests/Fixtures/AppWithStatelessAspect';

    /**
     * @return iterable<string, array{bool}>
     */
    public static function modes(): iterable
    {
        yield 'production' => [false];
        yield 'debug' => [true];
    }

    #[DataProvider('modes')]
    public function testAspectIsCreatedByItsContainerDefinition(bool $debug): void
    {
        // In debug mode the validating container loads, and weaves, the service while it is built
        TestApplication::initKernel(debug: $debug, configDirectory: self::CONFIG_DIRECTORY);
        $container = TestApplication::container(debug: $debug, configDirectory: self::CONFIG_DIRECTORY);
        $this->assertSame(0, StatelessAspect::$factoryCalls, 'Weaving must not create the aspect');

        $service = $container->get(ConfiguredService::class);
        $this->assertInstanceOf(ConfiguredService::class, $service);
        $this->assertSame('stateless configured', $service->describe());

        $this->assertSame(1, StatelessAspect::$factoryCalls);
        $this->assertSame($container->get(StatelessAspect::class), StatelessAspect::$advisedBy);
    }
}
