<?php
/*
 * Go! AOP framework
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace Go\Yii3\GoAopBridge\Tests\Support;

use Go\Yii3\GoAopBridge\AopBootstrap;
use Go\Yii3\GoAopBridge\Kernel\AspectYii3Kernel;
use Psr\Container\ContainerInterface;
use Yiisoft\Yii\Runner\BootstrapRunner;
use Yiisoft\Yii\Runner\Console\ConsoleApplicationRunner;

/**
 * The fixture Yii3 application of tests/Support/App, started the way its entry scripts do
 */
final class TestApplication
{
    public const string CONFIG_DIRECTORY = 'tests/Support/App/config';

    /**
     * Root path given to the runner: the bridge repository, whose vendor/ directory the application uses
     */
    public static function rootPath(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * Points the "@runtime" alias of the application to a directory of the current process
     *
     * The aspect kernel is a process-global singleton honouring the options of its first initialization only,
     * so every process weaving the fixtures gets its own cache.
     */
    public static function prepareRuntime(): string
    {
        $runtime = sys_get_temp_dir() . '/goaop-yii3-bridge-tests/' . getmypid();
        if (!is_dir($runtime)) {
            mkdir($runtime, 0777, true);
        }
        putenv('GOAOP_TEST_RUNTIME=' . $runtime);

        return $runtime;
    }

    /**
     * The entry line of the application: initializes the kernel before the runner builds the container
     */
    public static function initKernel(): AspectYii3Kernel
    {
        return AopBootstrap::init(rootPath: self::rootPath(), configDirectory: self::CONFIG_DIRECTORY);
    }

    public static function runner(bool $debug = true): ConsoleApplicationRunner
    {
        return new ConsoleApplicationRunner(
            rootPath: self::rootPath(),
            debug: $debug,
            configDirectory: self::CONFIG_DIRECTORY,
        );
    }

    /**
     * Builds the container like the console runner does, then runs the "bootstrap-console" callbacks
     */
    public static function container(bool $debug = true): ContainerInterface
    {
        $runner    = self::runner($debug);
        $container = $runner->getContainer();

        $bootstrap = $runner->getConfig()->get('bootstrap-console');
        (new BootstrapRunner($container, $bootstrap))->run();

        return $container;
    }
}
