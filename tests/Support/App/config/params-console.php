<?php

declare(strict_types=1);

use Go\Yii3\GoAopBridge\Tests\App\Command\GreetCommand;

return [
    'yiisoft/yii-console' => [
        'commands' => [
            'app:greet' => GreetCommand::class,
        ],
    ],
];
