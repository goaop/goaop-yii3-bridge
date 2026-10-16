<?php

declare(strict_types=1);

use Go\Yii3\GoAopBridge\Tests\App\Aspect\ConfiguredServiceAspect;

// Tag map of the container, the second way of tagging a service
return [
    'goaop.aspect' => [
        ConfiguredServiceAspect::class,
    ],
];
