<?php
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace Go\Yii3\GoAopBridge;

use Go\Yii3\GoAopBridge\Config\AspectList;
use Go\Yii3\GoAopBridge\Config\KernelOptions;
use Go\Yii3\GoAopBridge\Config\TaggedAspects;
use Go\Yii3\GoAopBridge\Kernel\AspectYii3Kernel;
use InvalidArgumentException;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Config\ConfigInterface;

/**
 * Initializes the aspect kernel from the entry script of the application, before the Yii runner starts
 *
 * Call it from every entry script (`public/index.php`, `yii`), between the creation of the runner and its run, with
 * the config of the runner:
 *
 * ```php
 * $runner = new HttpApplicationRunner(rootPath: dirname(__DIR__), debug: Environment::appDebug(), ...);
 * AopBootstrap::init($runner->getConfig(), rootPath: dirname(__DIR__));
 * $runner->run();
 * ```
 *
 * The options and aspects are read from that config, so the entry line sees the parameters of the runner
 * ({@see self::CONFIG_GROUP}) and tagged aspects from its common "di" and "di-tags" groups ({@see TaggedAspects}).
 * Weaving then covers every class the runner autoloads while it builds the container, which a callback of the
 * "bootstrap" config group can not.
 */
final class AopBootstrap
{
    /**
     * Config group of the bridge (config/kernel.php) returning the parameters the kernel is started with
     *
     * Its file is evaluated with the parameters of the config it is read from: with the config of the runner, these
     * are the parameters of the runner, e.g. "params-console" for the console runner.
     */
    public const string CONFIG_GROUP = 'goaop-kernel';

    /**
     * Static facade, never instantiated
     *
     * @codeCoverageIgnore
     */
    private function __construct() {}

    /**
     * Initializes the kernel once per process and returns it
     *
     * @param ConfigInterface $config   Config of the runner, {@see \Yiisoft\Yii\Runner\ApplicationRunner::getConfig()}
     * @param string          $rootPath Root path of the application, the default of the "@root" alias
     */
    public static function init(ConfigInterface $config, string $rootPath): AspectYii3Kernel
    {
        $kernel = AspectYii3Kernel::instance();
        if ($kernel->isInitialized()) {
            return $kernel;
        }

        if (!$config->has(self::CONFIG_GROUP)) {
            throw new InvalidArgumentException(sprintf(
                'The config has no "%s" group, is the package config merged (composer yii-config-rebuild)?',
                self::CONFIG_GROUP,
            ));
        }
        $params = $config->get(self::CONFIG_GROUP);

        $bridgeParams = $params[KernelOptions::PARAMS_KEY] ?? null;
        if (!is_array($bridgeParams)) {
            throw new InvalidArgumentException(sprintf(
                'The config parameters have no "%s" section, is the package config merged (composer yii-config-rebuild)?',
                KernelOptions::PARAMS_KEY,
            ));
        }
        $kernel->boot(
            KernelOptions::fromParams($bridgeParams, self::aliases($rootPath, $params['yiisoft/aliases'] ?? [])),
            AspectList::fromParams($bridgeParams)->merge(TaggedAspects::fromParams($bridgeParams)->collect($config)),
        );

        return $kernel;
    }

    /**
     * Creates the aliases of the application from the "yiisoft/aliases" parameters, "@root" defaults to the root path
     */
    private static function aliases(string $rootPath, mixed $params): Aliases
    {
        $aliases = ['@root' => $rootPath];
        foreach (is_array($params) && is_array($params['aliases'] ?? null) ? $params['aliases'] : [] as $alias => $path) {
            if (!is_string($alias) || !is_string($path)) {
                throw new InvalidArgumentException(sprintf(
                    'The "yiisoft/aliases" parameter "aliases" must map alias names to paths, %s given for %s.',
                    get_debug_type($path),
                    is_string($alias) ? '"' . $alias . '"' : get_debug_type($alias),
                ));
            }
            $aliases[$alias] = $path;
        }

        return new Aliases($aliases);
    }
}
