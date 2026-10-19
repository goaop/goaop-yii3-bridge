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
     * @var array<string, array<int, ConsoleApplicationRunner>>
     */
    private static array $runners = [];

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
     * The entry line of the application: initializes the kernel from the config of the runner, before the runner
     * builds the container
     */
    public static function initKernel(bool $debug = true, string $configDirectory = self::CONFIG_DIRECTORY): AspectYii3Kernel
    {
        return AopBootstrap::init(self::runner($debug, $configDirectory)->getConfig(), self::rootPath());
    }

    /**
     * Returns the console runner of the process, one per mode and config directory, as an entry script creates it
     */
    public static function runner(bool $debug = true, string $configDirectory = self::CONFIG_DIRECTORY): ConsoleApplicationRunner
    {
        return self::$runners[$configDirectory][(int) $debug] ??= new ConsoleApplicationRunner(
            rootPath: self::rootPath(),
            debug: $debug,
            configDirectory: $configDirectory,
        );
    }

    /**
     * Builds the container like the console runner does, then runs the "bootstrap-console" callbacks
     */
    public static function container(bool $debug = true, string $configDirectory = self::CONFIG_DIRECTORY): ContainerInterface
    {
        $runner    = self::runner($debug, $configDirectory);
        $container = $runner->getContainer();

        $bootstrap = $runner->getConfig()->get('bootstrap-console');
        (new BootstrapRunner($container, $bootstrap))->run();

        return $container;
    }
}
