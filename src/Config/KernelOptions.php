<?php
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace Go\Yii3\GoAopBridge\Config;

use Go\Aop\Features;
use InvalidArgumentException;
use Yiisoft\Aliases\Aliases;

/**
 * Options of the aspect kernel, built from the "goaop/goaop-yii3-bridge" application parameters
 *
 * Paths may use Yii aliases ("@root", "@runtime/aop", "@src"), they are resolved once, when the options are built.
 * A malformed parameter fails fast with a message naming it, instead of silently falling back to a default.
 */
final readonly class KernelOptions
{
    /**
     * Name of the parameters section of the bridge, the package name by the Yii3 convention
     */
    public const string PARAMS_KEY = 'goaop/goaop-yii3-bridge';

    /**
     * Environment variables consulted, in this order, when the "debug" parameter is null
     */
    public const array DEBUG_ENVIRONMENT_VARIABLES = ['GOAOP_DEBUG', 'APP_DEBUG'];

    /**
     * Environment variable consulted when the "enabled" parameter is null
     */
    public const string ENABLED_ENVIRONMENT_VARIABLE = 'GOAOP_ENABLED';

    /**
     * What the commands and the aspect container report when AOP is switched off
     */
    public const string DISABLED_MESSAGE = 'Go! AOP is disabled by the "enabled" parameter of goaop/goaop-yii3-bridge '
        . '(or GOAOP_ENABLED): the aspect kernel is not initialized and nothing is woven.';

    /**
     * Environment variable consulted when the "cacheDir" parameter is null, e.g. set by a container image to a
     * directory that no volume covers
     */
    public const string CACHE_DIR_ENVIRONMENT_VARIABLE = 'GOAOP_CACHE_DIR';

    /**
     * Cache directory when neither the "cacheDir" parameter nor the environment variable is set
     */
    public const string DEFAULT_CACHE_DIR = '@runtime/aop';

    /**
     * @param list<string>          $includePaths
     * @param list<string>          $excludePaths
     * @param positive-int|null     $parsedFilesLimit Null applies the default of the kernel
     * @param bool                  $enabled          False runs the application without the aspect kernel
     */
    public function __construct(
        public bool $debug,
        public string $appDir,
        public string $cacheDir,
        public ?int $cacheFileMode,
        public int $features,
        public array $includePaths,
        public array $excludePaths,
        public ?int $parsedFilesLimit = null,
        public bool $enabled = true,
    ) {}

    /**
     * Builds the options from the parameters section of the bridge
     *
     * @param array<mixed> $params Value of $params['goaop/goaop-yii3-bridge']
     */
    public static function fromParams(array $params, Aliases $aliases): self
    {
        return new self(
            debug: self::debug($params['debug'] ?? null),
            appDir: $aliases->get(self::nonEmptyString($params, 'appDir')),
            cacheDir: $aliases->get(self::cacheDir($params['cacheDir'] ?? null)),
            cacheFileMode: self::cacheFileMode($params['cacheFileMode'] ?? null),
            features: self::features($params['features'] ?? 0),
            includePaths: self::paths($params, 'includePaths', $aliases),
            excludePaths: self::paths($params, 'excludePaths', $aliases),
            parsedFilesLimit: self::parsedFilesLimit($params['parsedFilesLimit'] ?? null),
            enabled: self::enabled($params['enabled'] ?? null),
        );
    }

    /**
     * Returns the options in the format of {@see \Go\Core\AspectKernel::init()}
     *
     * @return array{
     *     debug: bool,
     *     appDir: string,
     *     cacheDir: string,
     *     cacheFileMode?: int,
     *     features: int,
     *     includePaths: list<string>,
     *     excludePaths: list<string>,
     *     parsedFilesLimit?: positive-int,
     * }
     */
    public function toArray(): array
    {
        $options = [
            'debug'        => $this->debug,
            'appDir'       => $this->appDir,
            'cacheDir'     => $this->cacheDir,
            'features'     => $this->features,
            'includePaths' => $this->includePaths,
            'excludePaths' => $this->excludePaths,
        ];
        // Without an explicit mode the kernel applies its own umask-based default
        if ($this->cacheFileMode !== null) {
            $options['cacheFileMode'] = $this->cacheFileMode;
        }
        if ($this->parsedFilesLimit !== null) {
            $options['parsedFilesLimit'] = $this->parsedFilesLimit;
        }

        return $options;
    }

    private static function debug(mixed $value): bool
    {
        if ($value === null) {
            foreach (self::DEBUG_ENVIRONMENT_VARIABLES as $variable) {
                $value = self::environmentVariable($variable);
                if ($value !== null) {
                    break;
                }
            }
            $value ??= false;
        }
        if (is_bool($value)) {
            return $value;
        }
        $debug = is_string($value) || is_int($value)
            ? filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)
            : null;
        if ($debug === null) {
            throw self::invalid('debug', 'a boolean, a boolean-like string or null', $value);
        }

        return $debug;
    }

    /**
     * Returns whether the aspect kernel starts: the parameter, else GOAOP_ENABLED, else true
     */
    private static function enabled(mixed $value): bool
    {
        $value ??= self::environmentVariable(self::ENABLED_ENVIRONMENT_VARIABLE) ?? true;
        if (is_bool($value)) {
            return $value;
        }
        $enabled = is_string($value) || is_int($value)
            ? filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)
            : null;
        if ($enabled === null) {
            throw self::invalid('enabled', 'a boolean, a boolean-like string or null', $value);
        }

        return $enabled;
    }

    /**
     * Returns the cache directory, an alias allowed: the parameter, else GOAOP_CACHE_DIR, else "@runtime/aop"
     */
    private static function cacheDir(mixed $value): string
    {
        if ($value === null) {
            $value = self::environmentVariable(self::CACHE_DIR_ENVIRONMENT_VARIABLE);
            // An empty variable counts as not set, as in "GOAOP_CACHE_DIR= ./yii ..."
            $value = $value === null || $value === '' ? self::DEFAULT_CACHE_DIR : $value;
        }
        if (!is_string($value) || $value === '') {
            throw self::invalid('cacheDir', 'a non-empty path string or null', $value);
        }

        return $value;
    }

    /**
     * Reads an environment variable the way the Yii3 application template does: process environment first
     */
    private static function environmentVariable(string $name): ?string
    {
        $value = getenv($name);
        if ($value === false) {
            $value = $_ENV[$name] ?? null;
        }

        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * @param array<mixed> $params
     */
    private static function nonEmptyString(array $params, string $name): string
    {
        $value = $params[$name] ?? null;
        if (!is_string($value) || $value === '') {
            throw self::invalid($name, 'a non-empty path string', $value);
        }

        return $value;
    }

    /**
     * Accepts an integer mode (0770) or octal digits in a string ("770", "0770"), e.g. from an environment variable
     */
    private static function cacheFileMode(mixed $value): ?int
    {
        if ($value === null || is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^0?[0-7]{3}$/', $value) === 1) {
            return (int) octdec($value);
        }

        throw self::invalid('cacheFileMode', 'an integer, a string of octal digits (e.g. "770") or null', $value);
    }

    private static function features(mixed $value): int
    {
        if (!is_int($value)) {
            throw self::invalid('features', 'an integer bitmask of Go\Aop\Features constants', $value);
        }
        if (($value & ~Features::ALL) !== 0) {
            throw new InvalidArgumentException(sprintf(
                'The "%s" parameter "features" must be a bitmask of Go\Aop\Features constants, %d contains unknown bits %d.',
                self::PARAMS_KEY,
                $value,
                $value & ~Features::ALL,
            ));
        }

        return $value;
    }

    /**
     * Accepts a positive integer or a string of decimal digits (e.g. from an environment variable)
     *
     * @return positive-int|null
     */
    private static function parsedFilesLimit(mixed $value): ?int
    {
        if (is_string($value) && ctype_digit($value)) {
            $value = (int) $value;
        }
        if ($value !== null && (!is_int($value) || $value < 1)) {
            throw self::invalid('parsedFilesLimit', 'a positive integer, a string of digits or null', $value);
        }

        return $value;
    }

    /**
     * @param array<mixed> $params
     *
     * @return list<string>
     */
    private static function paths(array $params, string $name, Aliases $aliases): array
    {
        $value = $params[$name] ?? [];
        if (!is_array($value)) {
            throw self::invalid($name, 'a list of paths', $value);
        }
        $paths = [];
        foreach ($value as $path) {
            if (!is_string($path) || $path === '') {
                throw self::invalid($name, 'a list of non-empty path strings', $path);
            }
            $paths[] = $aliases->get($path);
        }

        return $paths;
    }

    private static function invalid(string $name, string $expected, mixed $given): InvalidArgumentException
    {
        return new InvalidArgumentException(sprintf(
            'The "%s" parameter "%s" must be %s, %s given.',
            self::PARAMS_KEY,
            $name,
            $expected,
            get_debug_type($given),
        ));
    }
}
