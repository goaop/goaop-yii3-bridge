<?php

declare(strict_types=1);

use Go\Console\Command\DebugAdvisorCommand;
use Go\Console\Command\DebugAspectCommand;
use Go\Console\Command\DebugWeavingCommand;
use Go\Console\Command\OpcacheWarmupCommand;
use Go\Yii3\GoAopBridge\Command\WarmupCommand;
use Go\Yii3\GoAopBridge\Command\WarmupWorkerCommand;

// Class names only (::class): a class constant read here would load the class before the aspect kernel starts.
// The framework commands are registered as they are, under names of the "aop" namespace: they use the kernel the entry
// line initialized. Only "aop:warmup" is the bridge's own, it starts its workers through the entry script
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
