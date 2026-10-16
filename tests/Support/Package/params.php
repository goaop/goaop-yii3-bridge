<?php

declare(strict_types=1);

use Go\Yii3\GoAopBridge\Tests\App\Aspect\PackageAspect;

/*
 * Parameters of a third-party package contributing an aspect: the "params" group is merged recursively, so the
 * list is appended to the aspects of the bridge and of the application.
 */
return [
    'goaop/goaop-yii3-bridge' => [
        'aspects' => [
            PackageAspect::class,
        ],
    ],
];
