<?php

declare(strict_types=1);

use Go\Yii3\GoAopBridge\Command\DebugAdvisorCommand;
use Go\Yii3\GoAopBridge\Command\DebugAspectCommand;
use Go\Yii3\GoAopBridge\Command\DebugWeavingCommand;
use Go\Yii3\GoAopBridge\Command\OpcacheWarmupCommand;
use Go\Yii3\GoAopBridge\Command\WarmupCommand;
use Go\Yii3\GoAopBridge\Command\WarmupWorkerCommand;

// Class names only (::class): a class constant read here would load the class before the aspect kernel starts.
// The framework commands are registered under names of the "aop" namespace, as thin subclasses that report an
// application running without AOP ("enabled" false); they use the kernel the entry line initialized. "aop:warmup"
// also starts its workers through the entry script, "aop:opcache" adds the configuration files of the application
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
