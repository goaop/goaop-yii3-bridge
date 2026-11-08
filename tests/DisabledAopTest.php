<?php
/*
 * Go! AOP framework
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace Go\Yii3\GoAopBridge\Tests;

use Go\Core\AspectContainer;
use Go\Yii3\GoAopBridge\Config\KernelOptions;
use Go\Yii3\GoAopBridge\Kernel\AspectYii3Kernel;
use Go\Yii3\GoAopBridge\Tests\App\Service\GreetingService;
use Go\Yii3\GoAopBridge\Tests\Support\TestApplication;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * With GOAOP_ENABLED=0 (the "enabled" parameter null) the application runs without the aspect kernel: neither the
 * entry line nor the bootstrap fallback initializes it, and nothing is woven
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class DisabledAopTest extends TestCase
{
    protected function setUp(): void
    {
        putenv(KernelOptions::ENABLED_ENVIRONMENT_VARIABLE . '=0');
        TestApplication::prepareRuntime();
    }

    public function testEntryLineDoesNotInitializeTheKernel(): void
    {
        $kernel = TestApplication::initKernel();

        $this->assertFalse(AspectYii3Kernel::isInitialized());
        $this->assertSame(AspectYii3Kernel::instance(), $kernel);
    }

    public function testServicesRunUnwovenWithTheBootstrapFallback(): void
    {
        $container = TestApplication::container();
        $service   = $container->get(GreetingService::class);
        $this->assertInstanceOf(GreetingService::class, $service);

        $this->assertFalse(AspectYii3Kernel::isInitialized(), 'The bootstrap callback does not initialize it either');
        $this->assertSame('hello', $service->shout('hello'), 'No aspect is applied');
    }

    public function testAspectContainerReportsThatAopIsDisabled(): void
    {
        TestApplication::initKernel();
        $container = TestApplication::container();

        try {
            $container->get(AspectContainer::class);
            $this->fail('The aspect container of a disabled kernel was resolved');
        } catch (Throwable $exception) {
            $messages = [];
            for ($cause = $exception; $cause !== null; $cause = $cause->getPrevious()) {
                $messages[] = $cause->getMessage();
            }
            $this->assertContains(KernelOptions::DISABLED_MESSAGE, $messages, implode("\n", $messages));
        }
    }
}
