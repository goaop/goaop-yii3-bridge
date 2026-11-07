<?php

declare(strict_types=1);

use Go\Yii3\GoAopBridge\Command\DebugAdvisorCommand;
use Go\Yii3\GoAopBridge\Command\DebugAspectCommand;
use Go\Yii3\GoAopBridge\Command\DebugWeavingCommand;
use Go\Yii3\GoAopBridge\Command\OpcacheWarmupCommand;
use Go\Yii3\GoAopBridge\Command\WarmupCommand;
use Go\Yii3\GoAopBridge\Command\WarmupWorkerCommand;

// Class names only (::class): a class constant read here would load the class before the aspect kernel starts

return [
    'yiisoft/yii-console' => [
        'commands' => [
            'aop:warmup' => WarmupCommand::class,
            // Hidden ("|" prefix), started by "aop:warmup --workers=N" under this name (WarmupWorkerCommand::NAME)
            '|aop:warmup-worker' => WarmupWorkerCommand::class,
            'aop:opcache' => OpcacheWarmupCommand::class,
            'aop:debug:aspect' => DebugAspectCommand::class,
            'aop:debug:advisor' => DebugAdvisorCommand::class,
            'aop:debug:weaving' => DebugWeavingCommand::class,
        ],
    ],
];
