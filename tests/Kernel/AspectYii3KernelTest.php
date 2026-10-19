<?php
/*
 * Go! AOP framework
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace Go\Yii3\GoAopBridge\Tests\Kernel;

use Go\Core\AspectKernel;
use Go\Yii3\GoAopBridge\Config\AspectList;
use Go\Yii3\GoAopBridge\Config\KernelOptions;
use Go\Yii3\GoAopBridge\Kernel\AspectYii3Kernel;
use Go\Yii3\GoAopBridge\Tests\App\Aspect\GreetingAspect;
use Go\Yii3\GoAopBridge\Tests\App\Service\Journal;
use Go\Yii3\GoAopBridge\Tests\Fixtures\ArgumentAspect;
use Go\Yii3\GoAopBridge\Tests\Fixtures\StatelessAspect;
use LogicException;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use UnexpectedValueException;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AspectYii3KernelTest extends TestCase
{
    public function testInstanceIsTheProcessKernel(): void
    {
        $kernel = AspectYii3Kernel::instance();

        $this->assertSame($kernel, AspectKernel::getInstance());
        $this->assertSame($kernel, AspectYii3Kernel::instance());
        $this->assertFalse($kernel->isInitialized());
    }

    public function testBootInitializesKernelOnce(): void
    {
        $kernel = self::bootKernel([GreetingAspect::class]);
        $kernel->boot(
            new KernelOptions(true, '/elsewhere', '/elsewhere/aop', null, 0, [], []),
            AspectList::fromClassNames([]),
        );

        $this->assertTrue($kernel->isInitialized());
        $this->assertSame([GreetingAspect::class], $kernel->getAspects()->toArray());
        $this->assertSame(self::cacheDir(), $kernel->getOptions()['cacheDir']);
    }

    public function testAspectsAreRegisteredAsLazyServices(): void
    {
        $kernel = self::bootKernel([GreetingAspect::class]);
        $kernel->attachContainer(new Container(ContainerConfig::create()));

        $aspect = $kernel->getContainer()->getService(GreetingAspect::class);

        $this->assertInstanceOf(GreetingAspect::class, $aspect);
        $this->assertTrue(new ReflectionClass(GreetingAspect::class)->isUninitializedLazyObject($aspect));
        $this->assertSame(0, GreetingAspect::$instances);
    }

    public function testLazyAspectIsResolvedFromAttachedContainerOnFirstUse(): void
    {
        $kernel    = self::bootKernel([GreetingAspect::class]);
        $container = new Container(ContainerConfig::create());
        $kernel->attachContainer($container);
        $this->assertTrue($kernel->isContainerAttached());

        $aspect = $kernel->getContainer()->getService(GreetingAspect::class);
        new ReflectionClass(GreetingAspect::class)->initializeLazyObject($aspect);

        $this->assertSame(1, GreetingAspect::$instances);
        $this->assertSame(
            $container->get(GreetingAspect::class),
            new ReflectionClass(GreetingAspect::class)->initializeLazyObject($aspect),
        );
    }

    public function testServiceThatIsNotAnAspectIsReportedOnFirstUse(): void
    {
        $kernel = self::bootKernel([Journal::class]);
        $kernel->attachContainer(new Container(ContainerConfig::create()));
        $journal = $kernel->getContainer()->getService(Journal::class);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Aspect "' . Journal::class . '" must implement the "Go\Aop\Aspect" interface, the Yii container returned ' . Journal::class . '.');

        $journal->record('first use');
    }

    public function testStatefulAspectRequiresAttachedContainer(): void
    {
        $kernel = self::bootKernel([GreetingAspect::class]);
        $aspect = $kernel->getContainer()->getService(GreetingAspect::class);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('is requested before the Yii container is attached to the aspect kernel');

        new ReflectionClass(GreetingAspect::class)->initializeLazyObject($aspect);
    }

    public function testStatelessAspectIsResolvedFromTheAttachedContainer(): void
    {
        // PHP can not make it lazy: the factory runs when the aspect is retrieved, with the definition of the container
        $kernel    = self::bootKernel([ArgumentAspect::class]);
        $container = new Container(ContainerConfig::create()->withDefinitions([
            ArgumentAspect::class => ['__construct()' => ['label' => 'from the container']],
        ]));
        $kernel->attachContainer($container);

        $aspect = $kernel->getContainer()->getService(ArgumentAspect::class);

        $this->assertSame($container->get(ArgumentAspect::class), $aspect);
        $this->assertSame(['from the container'], ArgumentAspect::$labels);
    }

    public function testStatelessAspectNeededBeforeTheContainerIsAttachedIsReported(): void
    {
        $kernel = self::bootKernel([StatelessAspect::class]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Aspect "' . StatelessAspect::class . '" is needed by the weaver before the Yii container is attached');

        $kernel->getContainer()->getService(StatelessAspect::class);
    }

    /**
     * @param list<class-string> $aspects
     */
    private static function bootKernel(array $aspects): AspectYii3Kernel
    {
        $kernel = AspectYii3Kernel::instance();
        $kernel->boot(
            new KernelOptions(false, dirname(__DIR__, 2), self::cacheDir(), null, 0, [dirname(__DIR__) . '/Support/App/src/Service'], []),
            AspectList::fromClassNames($aspects),
        );

        return $kernel;
    }

    /**
     * Cache directory of the current process, the kernel only honours the options of its first initialization
     */
    private static function cacheDir(): string
    {
        $cacheDir = sys_get_temp_dir() . '/goaop-yii3-bridge-tests/kernel-' . getmypid();
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }

        return $cacheDir;
    }
}
