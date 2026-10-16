<?php
/*
 * Go! AOP framework
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace Go\Yii3\GoAopBridge\Tests\Weaving;

use Go\Yii3\GoAopBridge\Tests\App\Service\GreetingService;
use Go\Yii3\GoAopBridge\Tests\App\Service\Journal;
use Go\Yii3\GoAopBridge\Tests\App\Service\ReportRepository;
use Go\Yii3\GoAopBridge\Tests\App\Service\ReportService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use RuntimeException;

/**
 * Advices woven into services the Yii container autowires and injects
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AdviceWeavingTest extends WeavingTestCase
{
    public function testBeforeAndAfterAdvicesFireOnAutowiredService(): void
    {
        $container = $this->startApplication();

        $service = $container->get(GreetingService::class);
        $this->assertInstanceOf(GreetingService::class, $service);
        $this->assertSame('Hello, Yii!', $service->greet('Yii'));

        $journal = $container->get(Journal::class);
        $this->assertInstanceOf(Journal::class, $journal);
        $this->assertSame(['package:greet', 'before:greet', 'after:greet'], $journal->entries);
    }

    public function testAroundAdviceRewritesTheReturnValue(): void
    {
        $service = $this->startApplication()->get(GreetingService::class);
        $this->assertInstanceOf(GreetingService::class, $service);

        $this->assertSame('AOP!', $service->shout('aop'));
    }

    public function testAfterThrowingAdviceObservesTheException(): void
    {
        $container = $this->startApplication();
        $service   = $container->get(GreetingService::class);
        $this->assertInstanceOf(GreetingService::class, $service);

        try {
            $service->explode();
        } catch (RuntimeException $exception) {
            $this->assertSame('boom', $exception->getMessage());
        }

        $journal = $container->get(Journal::class);
        $this->assertInstanceOf(Journal::class, $journal);
        $this->assertSame(['afterThrowing:explode'], $journal->entries);
    }

    public function testAdviceFiresOnConstructorInjectedService(): void
    {
        $container = $this->startApplication();

        $service = $container->get(ReportService::class);
        $this->assertInstanceOf(ReportService::class, $service);

        $this->assertSame(['title' => 'Q3', 'id' => 42, 'audited' => true], $service->publish('Q3'));
    }

    public function testAdviceFiresOnContainerResolvedService(): void
    {
        $repository = $this->startApplication()->get(ReportRepository::class);
        $this->assertInstanceOf(ReportRepository::class, $repository);

        $this->assertSame(['id' => 42, 'audited' => true], $repository->create([]));
    }

    public function testWovenClassesAreCached(): void
    {
        $service = $this->startApplication()->get(ReportService::class);
        $this->assertInstanceOf(ReportService::class, $service);
        $service->publish('cache');

        $this->assertTrue($this->cacheContainsFileFor('ReportRepository'));
        $this->assertTrue($this->cacheContainsFileFor('ReportRepositoryOriginalTrait'));
    }
}
