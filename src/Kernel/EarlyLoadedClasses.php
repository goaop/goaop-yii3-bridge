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

namespace Go\Yii3\GoAopBridge\Kernel;

use Closure;
use Go\Instrument\ClassLoading\AopComposerLoader;
use Go\Instrument\Transformer\FilterInjectorTransformer;
use LogicException;
use ReflectionClass;

/**
 * Classes autoloaded while the entry line reads the configuration, before the aspect kernel starts
 *
 * The configuration is PHP: reading the parameters and the tags of the container definitions loads every class it
 * references, e.g. by a class constant or an enum case. Such a class is loaded before weaving starts and stays
 * unwoven for the rest of the process. Once the kernel runs, its class loader tells whether it transforms the class,
 * and such a class is reported instead of running without its advices.
 */
final class EarlyLoadedClasses
{
    /**
     * @var array<string, array{string, string}> Requested class name => [what was read, how to avoid the load]
     */
    private array $classes = [];

    /**
     * Runs the callback, recording every class autoloaded meanwhile
     *
     * @template T
     *
     * @param string       $origin What the callback reads, for error messages
     * @param string       $remedy How to avoid loading a class there, for error messages
     * @param Closure(): T $read
     *
     * @return T
     */
    public function record(string $origin, string $remedy, Closure $read): mixed
    {
        // Prepended and returning nothing: the class is still loaded by the next autoloader
        $recorder = function (string $className) use ($origin, $remedy): void {
            $this->classes[$className] ??= [$origin, $remedy];
        };
        spl_autoload_register($recorder, true, true);
        try {
            return $read();
        } finally {
            spl_autoload_unregister($recorder);
        }
    }

    /**
     * Ensures the aspect kernel, initialized now, does not transform any recorded class
     *
     * In production with a warm cache only the class loader is asked. Otherwise a recorded class within the include
     * paths is woven once, as the class loader would do it, which also fills the cache for it.
     *
     * @throws LogicException When the kernel transforms one of the classes, which then run without their advices
     */
    public function assertNotWoven(): void
    {
        $loader = $this->classes === [] ? null : self::findAopLoader();
        if ($loader === null) {
            return;
        }
        foreach ($this->classes as $className => [$origin, $remedy]) {
            $isLoaded = class_exists($className, false) || interface_exists($className, false)
                || trait_exists($className, false);
            $fileName = $isLoaded ? new ReflectionClass($className)->getFileName() : false;
            if ($fileName !== false && self::isWoven($loader, $className, $fileName)) {
                throw new LogicException(sprintf(
                    'Class "%s" was loaded while AopBootstrap::init() read %s, before the aspect kernel started, so it '
                    . 'runs without the changes the kernel weaves into it (its advices). The class is loaded by the '
                    . 'configuration itself, e.g. by a class constant or an enum case. %s',
                    $className,
                    $origin,
                    $remedy,
                ));
            }
        }
    }

    /**
     * Checks whether the kernel serves the class loaded from the given file transformed
     */
    private static function isWoven(AopComposerLoader $loader, string $className, string $fileName): bool
    {
        $file = $loader->findFile($className);
        if ($file === false) {
            return false;
        }
        // The original file, or (in production) the woven file of the cache
        if (!str_starts_with($file, FilterInjectorTransformer::PHP_FILTER_READ)) {
            return realpath($file) !== realpath($fileName);
        }

        // The transforming stream filter: debug mode, or a file the cache does not know yet
        return file_get_contents($file) !== file_get_contents($fileName);
    }

    private static function findAopLoader(): ?AopComposerLoader
    {
        foreach (spl_autoload_functions() as $loader) {
            if (is_array($loader) && $loader[0] instanceof AopComposerLoader) {
                return $loader[0];
            }
        }

        return null;
    }
}
