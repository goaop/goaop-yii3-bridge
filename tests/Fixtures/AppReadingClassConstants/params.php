<?php

declare(strict_types=1);

use Go\Yii3\GoAopBridge\Tests\App\Service\Tone;

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
        'excludePaths' => ['@src/Aspect'],
    ],
    // Loaded while the parameters are read: a class of the include paths that no aspect advises
    'app' => [
        'tone' => Tone::Loud,
    ],
];
