<?php

declare(strict_types=1);

// Merge plan of the ConfigFiles test, in the format of the yiisoft/config plugin: every kind of entry it writes
return [
    '/' => [
        'params' => [
            'acme/shop' => ['config/params.php', '?config/missing-optional.php'],
            '//' => ['acme/shop/config/override.php'],
            '/' => ['common/*.php'],
        ],
        'params-web' => [
            '/' => ['$params', 'web.php'],
        ],
    ],
    'prod' => [
        'params' => [
            '/' => ['environments/prod/params.php'],
        ],
    ],
];
