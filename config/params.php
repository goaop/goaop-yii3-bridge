<?php

declare(strict_types=1);

return [
    'goaop/goaop-yii3-bridge' => [
        /*
         * Debug mode: the engine re-weaves classes when their sources or the aspects change, and breakpoints in
         * the original sources work. Null follows the GOAOP_DEBUG, then the APP_DEBUG environment variable.
         * Keep it off in production: woven classes are then served straight from the cache (opcache-friendly).
         */
        'debug' => null,

        // Root directory of the application, only files below it are woven
        'appDir' => '@root',

        // Woven sources, proxies and advisor caches; fill it at deploy time with "./yii aop:warmup"
        'cacheDir' => '@runtime/aop',

        // Permissions of the cache files: an integer (0770) or octal digits ("770"); null applies 0770 minus umask
        'cacheFileMode' => null,

        // Bitmask of Go\Aop\Features flags, e.g. Features::PREBUILT_CACHE for read-only production file systems
        'features' => 0,

        // Only these directories are woven, keep it narrow
        'includePaths' => ['@src'],

        // These directories are never woven (the cache directory and the framework are always excluded)
        'excludePaths' => [],

        /*
         * Aspect classes (implementing Go\Aop\Aspect, advices declared with #[Before], #[After], #[Around],
         * #[AfterThrowing]). Every aspect is resolved from the Yii container, lazily: on the first call of one of
         * its advices. Configure its constructor arguments with a regular container definition.
         */
        'aspects' => [],
    ],
];
