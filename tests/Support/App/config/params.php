<?php

declare(strict_types=1);

use Go\Yii3\GoAopBridge\Tests\App\Aspect\ConfiguredServiceAspect;
use Go\Yii3\GoAopBridge\Tests\App\Aspect\GreetingAspect;
use Go\Yii3\GoAopBridge\Tests\App\Aspect\ReportAspect;

return [
    'yiisoft/aliases' => [
        'aliases' => [
            '@root' => dirname(__DIR__),
            '@src' => '@root/src',
            // Every test process weaves into its own cache directory
            '@runtime' => getenv('GOAOP_TEST_RUNTIME') ?: '@root/runtime',
        ],
    ],
    'goaop/goaop-yii3-bridge' => [
        'debug' => true,
        'excludePaths' => ['@src/Aspect'],
        'aspects' => [
            GreetingAspect::class,
            ReportAspect::class,
            ConfiguredServiceAspect::class,
        ],
    ],
];
