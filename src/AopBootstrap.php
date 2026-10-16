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
use Go\Yii3\GoAopBridge\Kernel\AspectYii3Kernel;
use InvalidArgumentException;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Config\Config;
use Yiisoft\Config\ConfigPaths;
use Yiisoft\Config\Modifier\RecursiveMerge;

/**
 * Initializes the aspect kernel from the entry script of the application, before the Yii runner starts
 *
 * Call it from `src/bootstrap.php` right after `vendor/autoload.php` is required:
 *
 * ```php
 * AopBootstrap::init(rootPath: dirname(__DIR__), environment: Environment::appEnv());
 * ```
 *
 * The options and aspects are read from the merged "params" config group (the bridge section and the
 * "yiisoft/aliases" aliases), the same configuration the runner uses later. Weaving then covers every class the
 * runner autoloads while it builds the container, which a callback of the "bootstrap" config group can not.
 */
final class AopBootstrap
{
    /**
     * Static facade, never instantiated
     *
     * @codeCoverageIgnore
     */
    private function __construct() {}

    /**
     * Initializes the kernel once per process and returns it
     *
     * The arguments mirror the ones of the Yii runners, pass the same values.
     */
    public static function init(
        string $rootPath,
        ?string $environment = null,
        string $configDirectory = 'config',
        string $vendorDirectory = 'vendor',
        string $mergePlanFile = '.merge-plan.php',
        string $paramsGroup = 'params',
    ): AspectYii3Kernel {
        $kernel = AspectYii3Kernel::instance();
        if ($kernel->isInitialized()) {
            return $kernel;
        }

        $config = new Config(
            new ConfigPaths($rootPath, $configDirectory, $vendorDirectory),
            $environment,
            [RecursiveMerge::groups($paramsGroup)],
            $paramsGroup,
            $mergePlanFile,
        );
        $params = $config->get($paramsGroup);

        $bridgeParams = $params[KernelOptions::PARAMS_KEY] ?? null;
        if (!is_array($bridgeParams)) {
            throw new InvalidArgumentException(sprintf(
                'The "%s" config group has no "%s" parameters, is the package config merged (composer yii-config-rebuild)?',
                $paramsGroup,
                KernelOptions::PARAMS_KEY,
            ));
        }
        $kernel->boot(
            KernelOptions::fromParams($bridgeParams, self::aliases($rootPath, $params['yiisoft/aliases'] ?? [])),
            AspectList::fromParams($bridgeParams),
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
