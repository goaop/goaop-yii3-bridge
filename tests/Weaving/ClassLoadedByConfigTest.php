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
use Go\Yii3\GoAopBridge\Tests\Support\TestApplication;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * A definition references a class constant of the advised service. The entry line reads the definitions only for the
 * opt-in tag lookup: by default the runner evaluates them after the kernel started, and the class is woven.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ClassLoadedByConfigTest extends WeavingTestCase
{
    private const string CONFIG_DIRECTORY = 'tests/Fixtures/AppReadingClassConstants';

    /**
     * @return iterable<string, array{bool}>
     */
    public static function modes(): iterable
    {
        yield 'debug mode' => [true];
        yield 'production mode' => [false];
    }

    #[DataProvider('modes')]
    public function testListedAspectAdvisesClassesReferencedByTheDefinitions(bool $debug): void
    {
        TestApplication::initKernel(debug: $debug, configDirectory: self::CONFIG_DIRECTORY);
        $this->assertFalse(class_exists(ConfiguredService::class, false));

        $service = TestApplication::container(debug: $debug, configDirectory: self::CONFIG_DIRECTORY)
            ->get(ConfiguredService::class);
        $this->assertInstanceOf(ConfiguredService::class, $service);

        $this->assertSame('woven configured', $service->describe());
    }

    #[DataProvider('modes')]
    public function testTagLookupLoadsTheReferencedClassesBeforeWeaving(bool $debug): void
    {
        // The documented limit of the opt-in tag lookup, the same in both modes
        TestApplication::initKernel(debug: $debug, configDirectory: self::CONFIG_DIRECTORY, environment: 'tagged');
        $this->assertTrue(class_exists(ConfiguredService::class, false));

        $service = TestApplication::container(debug: $debug, configDirectory: self::CONFIG_DIRECTORY, environment: 'tagged')
            ->get(ConfiguredService::class);
        $this->assertInstanceOf(ConfiguredService::class, $service);

        $this->assertSame('configured', $service->describe());
    }
}
