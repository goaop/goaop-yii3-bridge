<?php
/*
 * Go! AOP framework
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace Go\Yii3\GoAopBridge\Tests\Weaving;

use Go\Yii3\GoAopBridge\Kernel\AspectYii3Kernel;
use Go\Yii3\GoAopBridge\Tests\App\Aspect\ConfiguredServiceAspect;
use Go\Yii3\GoAopBridge\Tests\App\Aspect\ReportAspect;
use Go\Yii3\GoAopBridge\Tests\App\Service\ConfiguredService;
use Go\Yii3\GoAopBridge\Tests\App\Service\ReportRepository;
use Go\Yii3\GoAopBridge\Tests\Support\TestApplication;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Yiisoft\Di\Reference\TagReference;

/**
 * Aspects tagged "goaop.aspect" in the container config of the fixture application: ReportAspect through the
 * "tags" meta key of its definition (di.php), ConfiguredServiceAspect through the tag map (di-tags.php)
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class TaggedAspectTest extends WeavingTestCase
{
    public function testTaggedAspectsAreWovenWithTheEntryLine(): void
    {
        $container = $this->startApplication();

        $repository = $container->get(ReportRepository::class);
        $this->assertInstanceOf(ReportRepository::class, $repository);
        $this->assertSame(['id' => 42, 'audited' => true], $repository->create([]));

        $configured = $container->get(ConfiguredService::class);
        $this->assertInstanceOf(ConfiguredService::class, $configured);
        $this->assertSame('woven configured', $configured->describe());
    }

    public function testTaggedAspectsAreRegisteredByTheBootstrapFallback(): void
    {
        $container = TestApplication::container();

        $repository = $container->get(ReportRepository::class);
        $this->assertInstanceOf(ReportRepository::class, $repository);
        $this->assertSame(['id' => 42, 'audited' => true], $repository->create([]));
    }

    public function testBridgeFindsTheAspectsTheContainerTagged(): void
    {
        $container = $this->startApplication();

        $kernel = $container->get(AspectYii3Kernel::class);
        $this->assertInstanceOf(AspectYii3Kernel::class, $kernel);
        $registered = $kernel->getAspects()->toArray();
        $this->assertContains(ReportAspect::class, $registered);
        $this->assertContains(ConfiguredServiceAspect::class, $registered);

        // The container itself can only answer by creating every tagged service, the bridge reads the same tags
        $tagged = $container->get(TagReference::id('goaop.aspect'));
        $this->assertIsArray($tagged);
        $this->assertSame(
            [ConfiguredServiceAspect::class, ReportAspect::class],
            array_map(get_debug_type(...), $tagged),
        );
        $this->assertSame(
            [ConfiguredServiceAspect::class, ReportAspect::class],
            array_values(array_intersect($registered, [ConfiguredServiceAspect::class, ReportAspect::class])),
        );
    }
}
