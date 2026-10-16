<?php

declare(strict_types=1);

use Go\Yii3\GoAopBridge\KernelInitializer;
use Psr\Container\ContainerInterface;

return [
    static function (ContainerInterface $container): void {
        $initializer = $container->get(KernelInitializer::class);
        if (!$initializer instanceof KernelInitializer) {
            throw new UnexpectedValueException(sprintf(
                'Container service "%s" must be an instance of it, %s given.',
                KernelInitializer::class,
                get_debug_type($initializer),
            ));
        }
        $initializer->initialize($container);
    },
];
