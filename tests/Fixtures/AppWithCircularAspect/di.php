<?php

declare(strict_types=1);

use Go\Yii3\GoAopBridge\Tests\App\Service\ConfiguredService;
use Go\Yii3\GoAopBridge\Tests\Fixtures\CircularAspect;

return [
    ConfiguredService::class => [
        '__construct()' => ['prefix' => 'configured'],
    ],

    // Creating the aspect calls the method it advises: that advice can not run before the aspect exists
    CircularAspect::class => static fn(ConfiguredService $service): CircularAspect => new CircularAspect($service->describe()),
];
