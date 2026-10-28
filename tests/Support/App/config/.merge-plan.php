<?php

declare(strict_types=1);

/*
 * Merge plan of the fixture application, in the format written by the yiisoft/config composer plugin.
 *
 * The runner (whose config AopBootstrap::init() reads) uses the bridge repository as the root path, this directory as the config
 * directory and the vendor/ directory of the bridge. The bridge itself is not installed in vendor/, so its
 * package config is listed under the vendor override layer ("//"), whose paths are relative to vendor/.
 */
return [
    '/' => [
        'params' => [
            'yiisoft/aliases' => ['config/params.php'],
            '//' => ['../config/params.php', '../tests/Support/Package/params.php'],
            '/' => ['params.php'],
        ],
        'goaop-kernel' => [
            '//' => ['../config/kernel.php'],
        ],
        'params-console' => [
            'yiisoft/yii-console' => ['config/params-console.php'],
            'yiisoft/yii-event' => ['?config/params-console.php'],
            '//' => ['../config/params-console.php'],
            '/' => ['$params', 'params-console.php'],
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
        'di-tags' => [
            '/' => ['di-tags.php'],
        ],
        'di-tags-console' => [
            '/' => ['$di-tags'],
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
