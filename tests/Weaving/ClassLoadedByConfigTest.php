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
use Go\Yii3\GoAopBridge\Tests\App\Service\Tone;
use Go\Yii3\GoAopBridge\Tests\Support\TestApplication;
use LogicException;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The entry line reads the configuration before the kernel starts: a class the configuration references (here by a
 * class constant in a definition) is loaded then, and can not be woven any more
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ClassLoadedByConfigTest extends WeavingTestCase
{
    private const string CONFIG_DIRECTORY = 'tests/Fixtures/AppReadingClassConstants';

    public function testAdvisedClassLoadedWhileTheTagsAreReadIsReported(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'Class "' . ConfiguredService::class . '" was loaded while AopBootstrap::init() read the "di" and "di-tags" '
            . 'config groups for tagged aspects, before the aspect kernel started',
        );

        TestApplication::initKernel(configDirectory: self::CONFIG_DIRECTORY);
    }

    public function testListedAspectAdvisesClassesReferencedByTheDefinitions(): void
    {
        // The aspect is listed and the tag lookup is off: the runner loads the class, after the kernel started
        TestApplication::initKernel(configDirectory: self::CONFIG_DIRECTORY, environment: 'untagged');
        $this->assertFalse(class_exists(ConfiguredService::class, false));
        // Loaded while the parameters were read, but not transformed by the kernel: no error
        $this->assertTrue(enum_exists(Tone::class, false));

        $container = TestApplication::container(configDirectory: self::CONFIG_DIRECTORY, environment: 'untagged');
        $service   = $container->get(ConfiguredService::class);
        $this->assertInstanceOf(ConfiguredService::class, $service);

        $this->assertSame('woven configured', $service->describe());
    }
}
