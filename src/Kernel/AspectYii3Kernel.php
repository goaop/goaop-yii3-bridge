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
use Psr\Container\ContainerInterface;
use UnexpectedValueException;

/**
 * Aspect kernel of Yii3 applications
 *
 * Every configured aspect is registered as a deferred service of the aspect container: its class is not even
 * loaded until the weaver reads its advices, and the instance is resolved from the Yii container (with all its
 * dependencies, definitions and autowiring) only when one of its advices runs for the first time.
 *
 * The weaver reads the advices from the aspect class and never creates an aspect, so every aspect, also one without
 * instance properties, is the instance of the Yii container.
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

    private function resolver(): ContainerResolver
    {
        return $this->resolver ??= new ContainerResolver();
    }
}
