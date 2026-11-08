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

use Go\Yii3\GoAopBridge\Command\BuildProcess;
use Go\Yii3\GoAopBridge\Config\AspectList;
use Go\Yii3\GoAopBridge\Config\KernelOptions;
use Go\Yii3\GoAopBridge\Kernel\AspectYii3Kernel;
use Psr\Container\ContainerInterface;
use UnexpectedValueException;

/**
 * Connects the aspect kernel with the Yii container, run from the "bootstrap" config group of the bridge
 *
 * When the kernel was initialized from the entry script ({@see AopBootstrap::init()}), only the container is
 * attached. Otherwise the kernel is initialized here, from the options of the container. That fallback starts
 * weaving late: classes autoloaded while the container was built (e.g. every class with an array definition in
 * debug mode, service providers, the HTTP error handler) are not woven.
 */
final class KernelInitializer
{
    /**
     * @param mixed $consoleParams The "yiisoft/yii-console" parameters of the runner, {@see BuildProcess}
     */
    public function __construct(private readonly mixed $consoleParams = null) {}

    public function initialize(ContainerInterface $container): AspectYii3Kernel
    {
        $kernel = AspectYii3Kernel::instance();
        // Options and aspects are only resolved when the entry script did not initialize the kernel
        if (!AspectYii3Kernel::isInitialized()) {
            $options = self::service($container, KernelOptions::class);
            if (!$options->enabled) {
                // The application runs without AOP: no kernel, no container to attach, nothing to check
                return $kernel;
            }
            // Bootstrap callbacks run after this one may load classes the prebuilt cache misses (#808)
            if ((PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') && is_array($_SERVER['argv'] ?? null)) {
                BuildProcess::mark($this->consoleParams, array_values(array_filter($_SERVER['argv'], is_string(...))));
            }
            $kernel->boot(
                $options,
                self::service($container, AspectList::class),
            );
        }
        $kernel->attachContainer($container);

        // The aspect container ignores a registered service that is not an aspect; in debug mode such a
        // misconfiguration is reported right away (loading the aspect classes costs nothing worth saving there)
        if ($kernel->getOptions()['debug']) {
            $kernel->getAspects()->assertAspects();
        }

        return $kernel;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    private static function service(ContainerInterface $container, string $id): object
    {
        $service = $container->get($id);
        if (!$service instanceof $id) {
            throw new UnexpectedValueException(sprintf(
                'Container service "%s" must be an instance of it, %s given.',
                $id,
                get_debug_type($service),
            ));
        }

        return $service;
    }
}
