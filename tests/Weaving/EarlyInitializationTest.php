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
use Go\Yii3\GoAopBridge\Tests\App\Service\GreetingService;
use Go\Yii3\GoAopBridge\Tests\Support\TestApplication;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * In debug mode the Yii container reflects (and so autoloads) every class with an array definition while it is
 * built, before the "bootstrap" config group runs: only the entry line starts weaving early enough for them
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class EarlyInitializationTest extends WeavingTestCase
{
    public function testEntryLineWeavesArrayDefinedServicesInDebugMode(): void
    {
        $service = $this->startApplication(debug: true)->get(ConfiguredService::class);
        $this->assertInstanceOf(ConfiguredService::class, $service);

        $this->assertSame('woven configured', $service->describe());
    }

    public function testEntryLineWeavesArrayDefinedServicesInProductionMode(): void
    {
        $service = $this->startApplication(debug: false)->get(ConfiguredService::class);
        $this->assertInstanceOf(ConfiguredService::class, $service);

        $this->assertSame('woven configured', $service->describe());
    }

    public function testBootstrapFallbackMissesClassesLoadedWhileTheContainerIsBuilt(): void
    {
        $container = TestApplication::container(debug: true);

        $configured = $container->get(ConfiguredService::class);
        $this->assertInstanceOf(ConfiguredService::class, $configured);
        $this->assertSame('configured', $configured->describe());

        // Autowired classes are loaded on first use, after the bootstrap phase, and are woven
        $greeting = $container->get(GreetingService::class);
        $this->assertInstanceOf(GreetingService::class, $greeting);
        $this->assertSame('LATE!', $greeting->shout('late'));
    }
}
