<?php

declare(strict_types=1);

use Go\Yii3\GoAopBridge\Tests\App\Service\Tone;
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
    // Loaded while the entry line reads the parameters: checking that the kernel would not weave it needs the
    // aspects, which must wait for the container
    'app' => [
        'tone' => Tone::Loud,
    ],
];
