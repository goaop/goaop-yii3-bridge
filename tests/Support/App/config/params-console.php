<?php

declare(strict_types=1);

use Go\Yii3\GoAopBridge\Tests\App\Command\GreetCommand;

return [
    // Read by the common "di" config: the entry line must see the parameters of the console runner
    'app' => [
        'configuredPrefix' => 'configured',
    ],
    'yiisoft/yii-console' => [
        'commands' => [
            'app:greet' => GreetCommand::class,
        ],
    ],
];
