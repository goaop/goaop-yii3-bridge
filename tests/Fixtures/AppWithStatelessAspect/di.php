<?php

declare(strict_types=1);

use Go\Yii3\GoAopBridge\Tests\App\Service\ConfiguredService;
use Go\Yii3\GoAopBridge\Tests\Fixtures\StatelessAspect;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

return [
    LoggerInterface::class => NullLogger::class,

    // Validated, and so loaded, while the container is built in debug mode
    ConfiguredService::class => [
        '__construct()' => ['prefix' => 'configured'],
    ],

    StatelessAspect::class => static function (): StatelessAspect {
        StatelessAspect::$factoryCalls++;

        return new StatelessAspect();
    },
];
