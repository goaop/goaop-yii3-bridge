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

/**
 * The configuration files a merge plan of yiisoft/config lists: the files a Yii3 request includes besides the classes
 *
 * The merge plan, written by the yiisoft/config composer plugin, maps environments to groups, groups to packages and
 * packages to files: "/" is the application (relative to the config directory, the directory of the plan), "//" the
 * vendor override layer (relative to the vendor directory) and any other key a package (below the vendor directory).
 * A file may be optional ("?file") or a glob pattern ("web/*.php"); "$group" references another group. Every
 * environment and every group is read: the opcache file cache serves the web and the console, in any environment.
 */
final class ConfigFiles
{
    /**
     * File name of the merge plan the yiisoft/config plugin writes by default
     */
    public const string MERGE_PLAN_FILE_NAME = '.merge-plan.php';

    private const string ROOT_PACKAGE = '/';

    private const string VENDOR_OVERRIDE_PACKAGE = '//';

    private function __construct() {}

    /**
     * Returns the merge plans this process has read: the runner requires its plan when it creates its config
     *
     * @return list<string>
     */
    public static function loadedMergePlans(): array
    {
        return array_values(array_filter(
            get_included_files(),
            static fn (string $file): bool => basename($file) === self::MERGE_PLAN_FILE_NAME,
        ));
    }

    /**
     * Returns the existing files the merge plan lists, the plan itself first
     *
     * @return list<string>
     */
    public static function ofMergePlan(string $mergePlanFile, string $vendorDir): array
    {
        $mergePlan = require $mergePlanFile;
        if (!is_array($mergePlan)) {
            return [];
        }
        $configDir = dirname($mergePlanFile);
        $files     = [$mergePlanFile];
        foreach ($mergePlan as $groups) {
            foreach (is_array($groups) ? $groups : [] as $packages) {
                foreach (is_array($packages) ? $packages : [] as $package => $packageFiles) {
                    foreach (is_array($packageFiles) ? $packageFiles : [] as $file) {
                        if (!is_string($file) || $file === '' || str_starts_with($file, '$')) {
                            continue;
                        }
                        $path = self::packageDir((string) $package, $configDir, $vendorDir) . '/' . ltrim($file, '?');
                        $files = [...$files, ...self::existingFiles($path)];
                    }
                }
            }
        }

        return array_values(array_unique($files));
    }

    private static function packageDir(string $package, string $configDir, string $vendorDir): string
    {
        return match ($package) {
            self::ROOT_PACKAGE            => $configDir,
            self::VENDOR_OVERRIDE_PACKAGE => $vendorDir,
            default                       => $vendorDir . '/' . $package,
        };
    }

    /**
     * Expands a glob pattern as yiisoft/config does; an optional or missing file is left out
     *
     * @return list<string>
     */
    private static function existingFiles(string $path): array
    {
        if (!str_contains($path, '*')) {
            return is_file($path) ? [$path] : [];
        }

        return array_values(array_filter(glob($path) ?: [], is_file(...)));
    }
}
