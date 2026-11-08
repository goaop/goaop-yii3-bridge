<?php

declare(strict_types=1);

use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Yii3\GoAopBridge\Config\AspectList;
use Go\Yii3\GoAopBridge\Config\KernelOptions;
use Go\Yii3\GoAopBridge\Config\TaggedAspects;
use Go\Yii3\GoAopBridge\Kernel\AspectYii3Kernel;
use Go\Yii3\GoAopBridge\KernelInitializer;
use Psr\Container\ContainerInterface;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Config\ConfigInterface;

/** @var array{'goaop/goaop-yii3-bridge': array<mixed>, 'yiisoft/yii-console'?: mixed} $params */

return [
    KernelOptions::class => static fn(Aliases $aliases): KernelOptions => KernelOptions::fromParams(
        $params['goaop/goaop-yii3-bridge'],
        $aliases,
    ),
    // The runner registers its config as ConfigInterface: tagged aspects are read from it, without creating them
    AspectList::class => static fn(?ConfigInterface $config = null): AspectList => AspectList::fromParams(
        $params['goaop/goaop-yii3-bridge'],
    )->merge(
        $config === null
            ? AspectList::fromClassNames([])
            : TaggedAspects::fromParams($params['goaop/goaop-yii3-bridge'])->collect($config),
    ),
    AspectYii3Kernel::class => static fn(): AspectYii3Kernel => AspectYii3Kernel::instance(),
    AspectKernel::class => static fn(AspectYii3Kernel $kernel): AspectKernel => $kernel,
    AspectContainer::class => static function (AspectKernel $kernel, ContainerInterface $container): AspectContainer {
        // Without AOP ("enabled" false) there is no aspect container, say why instead of failing on the kernel
        if (!AspectKernel::isInitialized()) {
            $options = $container->get(KernelOptions::class);
            if ($options instanceof KernelOptions && !$options->enabled) {
                throw new LogicException(KernelOptions::DISABLED_MESSAGE);
            }
        }

        return $kernel->getContainer();
    },
    // The console commands of the runner tell whether the process builds the AOP cache (#808)
    KernelInitializer::class => static fn(): KernelInitializer => new KernelInitializer(
        $params['yiisoft/yii-console'] ?? null,
    ),
];
