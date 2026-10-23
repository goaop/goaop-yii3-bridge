<?php

declare(strict_types=1);

use Go\Yii3\GoAopBridge\Tests\Fixtures\StatelessAspect;

return [
    'yiisoft/aliases' => [
        'aliases' => [
            '@root' => dirname(__DIR__, 2) . '/Support/App',
            '@src' => '@root/src',
            '@runtime' => getenv('GOAOP_TEST_RUNTIME') ?: '@root/runtime',
        ],
    ],
    'goaop/goaop-yii3-bridge' => [
        'debug' => true,
        'aspects' => [
            StatelessAspect::class,
        ],
    ],
];
