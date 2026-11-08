<?php

declare(strict_types=1);

return [
    'goaop/goaop-yii3-bridge' => [
        /*
         * False runs the application without the aspect kernel: nothing is woven, the "aop:*" commands report that
         * AOP is disabled. Null follows the GOAOP_ENABLED environment variable, then true
         */
        'enabled' => null,

        /*
         * Debug mode: the engine re-weaves classes when their sources or the aspects change, and breakpoints in
         * the original sources work. Null follows the GOAOP_DEBUG, then the APP_DEBUG environment variable.
         * Keep it off in production: woven classes are then served straight from the cache (opcache-friendly).
         */
        'debug' => null,

        // Root directory of the application, only files below it are woven
        'appDir' => '@root',

        /*
         * Woven sources, proxies and advisor caches; fill it at deploy time with "./yii aop:warmup". Null follows the
         * GOAOP_CACHE_DIR environment variable, then "@runtime/aop". Keep it out of shared or persistent volumes: a
         * container image that mounts a volume on the runtime directory would serve the cache of an older release
         */
        'cacheDir' => null,

        // Permissions of the cache files: an integer (0770) or octal digits ("770"); null applies 0770 minus umask
        'cacheFileMode' => null,

        /*
         * Bitmask of Go\Aop\Features flags: Features::PREBUILT_CACHE trusts the cache built by "./yii aop:warmup"
         * (read-only production file systems, a class the cache misses fails), Features::PARTIAL_REFLECTION weaves
         * classes whose parent class, interface or trait Composer can not locate, Features::INTERCEPT_INCLUDES
         * weaves files included with include/require
         */
        'features' => 0,

        // Only these directories are woven, keep it narrow
        'includePaths' => ['@src'],

        // These directories are never woven (the cache directory and the framework are always excluded)
        'excludePaths' => [],

        /*
         * Number of parsed files kept in memory while weaving (also the default of "aop:warmup --cached-files"): more
         * is faster for a large code base, fewer uses less memory. A positive integer or digits; null applies the
         * default of the kernel (100)
         */
        'parsedFilesLimit' => null,

        /*
         * Aspect classes (implementing Go\Aop\Aspect, advices declared with #[Before], #[After], #[Around],
         * #[AfterThrowing]). Every aspect is resolved from the Yii container, lazily: on the first call of one of
         * its advices. Configure its constructor arguments with a regular container definition.
         */
        'aspects' => [],

        /*
         * Opt-in container tag of aspect services, e.g. 'goaop.aspect': every definition of the common "di" config
         * group tagged with it, and every service listed under it in a common "di-tags" group, is registered as an
         * aspect too, just as lazily. The entry line then evaluates these groups before the kernel starts, so a class
         * they reference (a class constant, an enum case) is loaded too early to be woven: prefer "aspects".
         */
        'aspectTag' => null,
    ],
];
