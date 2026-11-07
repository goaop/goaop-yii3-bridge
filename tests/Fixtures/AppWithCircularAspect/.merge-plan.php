<?php

declare(strict_types=1);

/*
 * An application with an aspect whose creation runs its own advice: the factory of its container definition
 * calls the advised method of a dependency
 */
return [
    '/' => [
        'params' => [
            'yiisoft/aliases' => ['config/params.php'],
            '//' => ['../config/params.php'],
            '/' => ['params.php'],
        ],
        'goaop-kernel' => [
            '//' => ['../config/kernel.php'],
        ],
        'params-console' => [
            'yiisoft/yii-console' => ['config/params-console.php'],
            'yiisoft/yii-event' => ['?config/params-console.php'],
            '//' => ['../config/params-console.php'],
            '/' => ['$params'],
        ],
        'di' => [
            'yiisoft/aliases' => ['config/di.php'],
            'yiisoft/yii-event' => ['?config/di.php'],
            '//' => ['../config/di.php'],
            '/' => ['di.php'],
        ],
        'di-console' => [
            'yiisoft/yii-console' => ['config/di-console.php'],
            'yiisoft/yii-event' => ['?config/di-console.php'],
            '/' => ['$di'],
        ],
        'events' => [],
        'events-console' => [
            'yiisoft/yii-console' => ['config/events-console.php'],
            '/' => ['$events'],
        ],
        'bootstrap' => [
            '//' => ['../config/bootstrap.php'],
        ],
        'bootstrap-console' => [
            '/' => ['$bootstrap'],
        ],
    ],
];
