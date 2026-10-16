<?php

declare(strict_types=1);

use Go\Yii3\GoAopBridge\Command\DebugAdvisorCommand;
use Go\Yii3\GoAopBridge\Command\DebugAspectCommand;
use Go\Yii3\GoAopBridge\Command\DebugWeavingCommand;
use Go\Yii3\GoAopBridge\Command\WarmupCommand;

return [
    'yiisoft/yii-console' => [
        'commands' => [
            'aop:warmup' => WarmupCommand::class,
            'aop:debug:aspect' => DebugAspectCommand::class,
            'aop:debug:advisor' => DebugAdvisorCommand::class,
            'aop:debug:weaving' => DebugWeavingCommand::class,
        ],
    ],
];
