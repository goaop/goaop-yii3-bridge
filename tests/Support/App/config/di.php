<?php

declare(strict_types=1);

use Go\Yii3\GoAopBridge\Tests\App\Aspect\ReportAspect;
use Go\Yii3\GoAopBridge\Tests\App\Service\ConfiguredService;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

return [
    LoggerInterface::class => NullLogger::class,

    // An array definition: in debug mode the container reflects (and so autoloads) the class while it is built
    ConfiguredService::class => [
        '__construct()' => ['prefix' => 'configured'],
    ],

    // Aspects are ordinary services: their constructor arguments come from regular definitions
    ReportAspect::class => [
        '__construct()' => ['label' => 'audited'],
    ],
];
