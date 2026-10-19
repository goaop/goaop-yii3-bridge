<?php

declare(strict_types=1);

use Go\Yii3\GoAopBridge\Tests\App\Aspect\ConfiguredServiceAspect;

// The remedy: the aspect is listed, the entry line does not read the container definitions
return [
    'goaop/goaop-yii3-bridge' => [
        'aspectTag' => null,
        'aspects' => [
            ConfiguredServiceAspect::class,
        ],
    ],
];
