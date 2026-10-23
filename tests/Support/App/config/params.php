<?php

declare(strict_types=1);

use Go\Yii3\GoAopBridge\Tests\App\Aspect\GreetingAspect;

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
        // The tag lookup is opt-in: ReportAspect and ConfiguredServiceAspect are tagged (di.php, di-tags.php)
        'aspectTag' => 'goaop.aspect',
        'aspects' => [
            GreetingAspect::class,
        ],
    ],
];
