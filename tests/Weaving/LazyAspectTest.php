<?php
/*
 * Go! AOP framework
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace Go\Yii3\GoAopBridge\Tests\Weaving;

use Go\Core\AspectContainer;
use Go\Yii3\GoAopBridge\Tests\App\Aspect\GreetingAspect;
use Go\Yii3\GoAopBridge\Tests\App\Service\GreetingService;
use Go\Yii3\GoAopBridge\Tests\App\Service\Journal;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use ReflectionClass;

/**
 * Aspects are resolved from the Yii container on the first call of one of their advices, with their dependencies
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class LazyAspectTest extends WeavingTestCase
{
    public function testAspectIsNotCreatedBeforeItsFirstAdviceRuns(): void
    {
        $container = $this->startApplication();

        // Weaving the service reads the advices of the aspect, without creating it
        $service = $container->get(GreetingService::class);
        $this->assertInstanceOf(GreetingService::class, $service);
        $this->assertSame(0, GreetingAspect::$instances);

        $service->greet('first');
        $service->greet('second');

        $this->assertSame(1, GreetingAspect::$instances);
    }

    public function testAspectIsBackedByTheSharedInstanceOfTheYiiContainer(): void
    {
        $container = $this->startApplication();
        $service   = $container->get(GreetingService::class);
        $this->assertInstanceOf(GreetingService::class, $service);
        $service->greet('Yii');

        // The aspect container holds a native lazy proxy, initialized with the instance of the Yii container
        $aspectContainer = $container->get(AspectContainer::class);
        $this->assertInstanceOf(AspectContainer::class, $aspectContainer);
        $proxy = $aspectContainer->getService(GreetingAspect::class);
        $this->assertSame(
            $container->get(GreetingAspect::class),
            new ReflectionClass(GreetingAspect::class)->initializeLazyObject($proxy),
        );
        $this->assertSame(1, GreetingAspect::$instances);

        // Its dependency is the shared Journal service of the Yii container
        $journal = $container->get(Journal::class);
        $this->assertInstanceOf(Journal::class, $journal);
        $this->assertContains('before:greet', $journal->entries);
    }

    public function testAspectContainerIsInjectable(): void
    {
        $aspectContainer = $this->startApplication()->get(AspectContainer::class);
        $this->assertInstanceOf(AspectContainer::class, $aspectContainer);

        $this->assertTrue($aspectContainer->has(GreetingAspect::class));
    }
}
