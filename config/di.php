<?php

declare(strict_types=1);

use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Yii3\GoAopBridge\Config\AspectList;
use Go\Yii3\GoAopBridge\Config\KernelOptions;
use Go\Yii3\GoAopBridge\Kernel\AspectYii3Kernel;
use Yiisoft\Aliases\Aliases;

/** @var array{'goaop/goaop-yii3-bridge': array<mixed>} $params */

return [
    KernelOptions::class => static fn(Aliases $aliases): KernelOptions => KernelOptions::fromParams(
        $params['goaop/goaop-yii3-bridge'],
        $aliases,
    ),
    AspectList::class => static fn(): AspectList => AspectList::fromParams($params['goaop/goaop-yii3-bridge']),
    AspectYii3Kernel::class => static fn(): AspectYii3Kernel => AspectYii3Kernel::instance(),
    AspectKernel::class => static fn(AspectYii3Kernel $kernel): AspectKernel => $kernel,
    AspectContainer::class => static fn(AspectKernel $kernel): AspectContainer => $kernel->getContainer(),
];
