<?php

declare(strict_types=1);

use Go\Yii3\GoAopBridge\Tests\App\Aspect\ConfiguredServiceAspect;
use Go\Yii3\GoAopBridge\Tests\App\Service\ConfiguredService;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

return [
    LoggerInterface::class => NullLogger::class,

    // The constant loads the class while the entry line reads the tags, before the kernel starts
    ConfiguredService::class => [
        '__construct()' => ['prefix' => ConfiguredService::DEFAULT_PREFIX],
    ],

    ConfiguredServiceAspect::class => [
        'tags' => ['goaop.aspect'],
    ],
];
