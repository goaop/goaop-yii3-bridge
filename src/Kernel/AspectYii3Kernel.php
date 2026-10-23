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

use Go\Aop\Aspect;
use Go\Aop\Exception\AspectException;
use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Yii3\GoAopBridge\Config\AspectList;
use Go\Yii3\GoAopBridge\Config\KernelOptions;
use LogicException;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use UnexpectedValueException;

/**
 * Aspect kernel of Yii3 applications
 *
 * Every configured aspect is registered as a deferred service of the aspect container: its class is not even
 * loaded until the weaver reads its advices, and the instance is resolved from the Yii container (with all its
 * dependencies, definitions and autowiring) only when one of its advices runs for the first time.
 *
 * An aspect is always the instance of the Yii container. One without instance properties can not be lazy: when the
 * weaver needs it before the container is attached, that is reported ({@see self::statelessAspectWithoutContainer()}).
 */
final class AspectYii3Kernel extends AspectKernel
{
    private ?AspectList $aspects = null;

    private ?ContainerResolver $resolver = null;

    /**
     * Returns the kernel of the application
     *
     * @throws AspectException When another kernel class was instantiated in this process before
     */
    public static function instance(): self
    {
        $kernel = self::getInstance();
        if (!$kernel instanceof self) {
            throw new AspectException(sprintf(
                'The aspect kernel of this process is "%s", the Yii3 bridge requires "%s".',
                $kernel::class,
                self::class,
            ));
        }

        return $kernel;
    }

    /**
     * Initializes the kernel and registers the aspects as lazily resolved services
     *
     * Only the first call initializes the kernel, as for {@see AspectKernel::init()}.
     */
    public function boot(KernelOptions $options, AspectList $aspects): void
    {
        if ($this->wasInitialized) {
            return;
        }
        $this->aspects = $aspects;
        $this->init($options->toArray());
    }

    /**
     * Returns the aspects registered by {@see boot()}
     */
    public function getAspects(): AspectList
    {
        return $this->aspects ?? AspectList::fromClassNames([]);
    }

    public function isInitialized(): bool
    {
        return $this->wasInitialized;
    }

    /**
     * Attaches the Yii container the aspects are resolved from
     *
     * It also becomes the delegate of the aspect container: an id the aspect container does not know (e.g. a service a
     * custom aspect factory or a framework command asks for) is resolved from the Yii container.
     */
    public function attachContainer(ContainerInterface $container): void
    {
        $this->resolver()->attach($container);
        $this->getContainer()->setDelegate($container);
    }

    public function isContainerAttached(): bool
    {
        return $this->resolver()->isAttached();
    }

    protected function configureAop(AspectContainer $container): void
    {
        $resolver = $this->resolver();
        foreach ($this->aspects ?? [] as $aspectClass) {
            $container->addLazyService($aspectClass, static function () use ($resolver, $aspectClass): object {
                if (!$resolver->isAttached() && self::isStateless($aspectClass)) {
                    throw self::statelessAspectWithoutContainer($aspectClass);
                }
                $aspect = $resolver->get($aspectClass);
                if (!$aspect instanceof Aspect) {
                    throw new UnexpectedValueException(sprintf(
                        'Aspect "%s" must implement the "%s" interface, the Yii container returned %s.',
                        $aspectClass,
                        Aspect::class,
                        get_debug_type($aspect),
                    ));
                }

                return $aspect;
            });
        }
    }

    /**
     * Checks whether the class has no instance properties, which PHP can not create a lazy proxy for
     *
     * @param class-string $className
     */
    private static function isStateless(string $className): bool
    {
        foreach (new ReflectionClass($className)->getProperties() as $property) {
            if (!$property->isStatic()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Reports an aspect needed by the weaver before the Yii container is attached
     *
     * Deferred services are native lazy proxies, so the factory of an aspect normally runs on the first call of one
     * of its advices only. PHP can not make a class without instance properties lazy though: the aspect container
     * invokes the factory of such an aspect as soon as the weaver reads the aspects. When a class is woven while the
     * Yii container is built (e.g. in debug mode, where the container autoloads every class with an array definition
     * to validate it), the aspect can not come from the container yet. Creating it otherwise would ignore its
     * container definition and leave the container with an instance of its own, so it is an error: only the timing
     * of the weaving would decide how an aspect is created.
     *
     * @param class-string $aspectClass
     */
    private static function statelessAspectWithoutContainer(string $aspectClass): LogicException
    {
        return new LogicException(sprintf(
            'Aspect "%s" is needed by the weaver before the Yii container is attached to the aspect kernel, so it can '
            . 'not be resolved from the container: PHP creates no lazy proxy for a class without instance properties, '
            . 'its factory runs as soon as the weaver reads the aspects. This happens when a class is woven while the '
            . 'container is built, e.g. in debug mode, where the container loads the classes of array definitions to '
            . 'validate them. Give the aspect an instance property (e.g. a dependency injected by its constructor): '
            . 'it is then resolved from the container on the first call of one of its advices.',
            $aspectClass,
        ));
    }

    private function resolver(): ContainerResolver
    {
        return $this->resolver ??= new ContainerResolver();
    }
}
