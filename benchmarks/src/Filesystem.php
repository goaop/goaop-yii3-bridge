<?php
/*
 * Go! AOP framework
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace Go\Yii3\GoAopBridge\Benchmarks;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final class Filesystem
{
    private function __construct() {}

    /**
     * Removes a file or a directory with its contents, a missing path is ignored
     */
    public static function remove(string $path): void
    {
        if (is_file($path) || is_link($path)) {
            self::check(unlink($path), $path);

            return;
        }
        if (!is_dir($path)) {
            return;
        }
        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($entries as $entry) {
            if ($entry instanceof SplFileInfo) {
                $name = $entry->getPathname();
                self::check($entry->isDir() && !$entry->isLink() ? rmdir($name) : unlink($name), $name);
            }
        }
        self::check(rmdir($path), $path);
    }

    public static function ensureDirectory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0777, true) && !is_dir($path)) {
            throw new RuntimeException(sprintf('Can not create the directory "%s".', $path));
        }
    }

    private static function check(bool $removed, string $path): void
    {
        if (!$removed) {
            throw new RuntimeException(sprintf('Can not remove "%s".', $path));
        }
    }
}
