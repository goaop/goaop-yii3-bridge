<?php
/*
 * Go! AOP framework
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace Go\Yii3\GoAopBridge\Tests\Weaving;

use FilesystemIterator;
use Go\Yii3\GoAopBridge\Tests\Support\TestApplication;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Base class of the tests weaving the fixture application of tests/Support/App
 *
 * Subclasses must run in separate processes: the aspect kernel is a process-global singleton wrapping the composer
 * autoloader, and a woven class stays woven for the rest of the process. The fixture classes must not be
 * referenced before the kernel is initialized (::class constants are fine, they do not autoload).
 */
abstract class WeavingTestCase extends TestCase
{
    protected string $runtime;

    protected function setUp(): void
    {
        $this->runtime = TestApplication::prepareRuntime();
    }

    /**
     * Starts the application the recommended way: entry line first, then the runner
     */
    protected function startApplication(bool $debug = true): ContainerInterface
    {
        TestApplication::initKernel($debug);

        return TestApplication::container($debug);
    }

    protected function cacheContainsFileFor(string $shortClassName): bool
    {
        $cacheDir = $this->runtime . '/aop';
        if (!is_dir($cacheDir)) {
            return false;
        }
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($cacheDir, FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file instanceof SplFileInfo && $file->getFilename() === $shortClassName . '.php') {
                return true;
            }
        }

        return false;
    }
}
