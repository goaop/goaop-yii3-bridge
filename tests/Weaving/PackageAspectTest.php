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
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * A package contributes an aspect through its own params (tests/Support/Package/params.php)
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class PackageAspectTest extends WeavingTestCase
{
    public function testAspectContributedByPackageIsWoven(): void
    {
        $container = $this->startApplication();
        $service   = $container->get(GreetingService::class);
        $this->assertInstanceOf(GreetingService::class, $service);

        $service->greet('package');

        $journal = $container->get(Journal::class);
        $this->assertInstanceOf(Journal::class, $journal);
        $this->assertSame('package:greet', $journal->entries[0]);
    }
}
