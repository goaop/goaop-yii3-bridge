<?php

declare(strict_types=1);

/*
 * An application whose configuration references classes of the fixture application (tests/Support/App/src), e.g.
 * by a class constant: the entry line loads them while it reads the configuration, before the kernel starts.
 *
 * The aspect is listed in the params; the "tagged" environment enables the tag lookup on top.
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
    'tagged' => [
        'params' => [
            '/' => ['tagged/params.php'],
        ],
    ],
];
