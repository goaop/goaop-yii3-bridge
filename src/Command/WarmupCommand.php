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

namespace Go\Yii3\GoAopBridge\Command;

use Go\Console\Command\CacheWarmupCommand as FrameworkCacheWarmupCommand;
use Go\Core\AspectKernel;
use Go\Instrument\ClassLoading\CacheWarmer;
use Go\Yii3\GoAopBridge\Kernel\AspectYii3Kernel;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Weaves every class below the include paths into the cache directory ahead of time, e.g. during deployment
 *
 * With `--workers=N` the files are woven by N worker processes: each of them runs the entry script of this process
 * again with the hidden {@see WarmupWorkerCommand}, so the application boots in the worker with the same environment,
 * configuration and aspects. That needs the entry line: a process whose kernel was initialized by the bootstrap
 * fallback, or that does not run the entry script that initialized it (e.g. a test), weaves in this process.
 */
#[AsCommand(
    name: 'aop:warmup',
    description: 'Warms up the Go! AOP cache with woven classes',
    help: <<<'EOT'
Weaves every class below the configured include paths of the aspect kernel and stores the woven sources and
proxies in its cache directory, so no weaving happens on the first real request.

  <info>%command.full_name%</info>
  <info>%command.full_name% --fail-fast</info>

The command fails when a file cannot be woven, so a deployment never ships an incomplete cache.
Errors are written to stderr; use <comment>--fail-fast</comment> to stop at the first one.

Large applications are woven faster in parallel worker processes, each running this entry script again:

  <info>%command.full_name% --workers=8</info>

Each weaving process keeps the last parsed files in memory (the <comment>parsedFilesLimit</comment> parameter of
the bridge, 100 by default); set <comment>--cached-files</comment> higher to re-parse less, lower to use less memory.

With <comment>--cache-dir</comment> the cache is built in another directory, e.g. to prepare the cache of the next
release next to the running one: the kernel keeps using its own directory, and a cache built this way is complete
and location independent.

<comment>--preload</comment> writes the opcache.preload script of the cache, <info>_preload.php</info> in the cache
directory or the file given as <comment>--preload=FILE</comment>. Point opcache.preload at it (with
opcache.preload_user) and every woven class is linked once when PHP starts instead of being loaded by each request.
EOT,
)]
final class WarmupCommand extends FrameworkCacheWarmupCommand
{
    protected function createCacheWarmer(
        OutputInterface $output,
        bool $failFast = false,
        int $workers = 1,
        int $cachedFiles = AspectKernel::DEFAULT_PARSED_FILES_LIMIT,
        ?string $cacheDir = null,
        ?string $preloadFile = null,
    ): CacheWarmer {
        if ($workers > 1 && $this->getWorkerCommandLine($cachedFiles, $cacheDir) === []) {
            $errorOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
            $errorOutput->writeln(
                '<comment>Worker processes can only be started from the entry script that initialized the kernel '
                . '(AopBootstrap::init()), weaving in this process.</comment>',
            );
        }

        return parent::createCacheWarmer($output, $failFast, $workers, $cachedFiles, $cacheDir, $preloadFile);
    }

    /**
     * Returns the command line of a worker: the entry script of this process running {@see WarmupWorkerCommand}
     *
     * @return list<string> Empty when no worker can be started from this process
     */
    protected function getWorkerCommandLine(int $cachedFiles = AspectKernel::DEFAULT_PARSED_FILES_LIMIT, ?string $cacheDir = null): array
    {
        // The kernel the entry line initialized, as the framework command finds it
        $kernel      = AspectKernel::isInitialized() ? AspectKernel::getInstance() : null;
        $entryScript = $kernel instanceof AspectYii3Kernel ? $kernel->getEntryScript() : null;
        $runningScript = $_SERVER['SCRIPT_FILENAME'] ?? null;
        // Only the script running this process is known to start the console application
        if ($entryScript === null || !is_string($runningScript) || realpath($runningScript) !== realpath($entryScript)
            || !function_exists('proc_open')
        ) {
            return [];
        }

        $commandLine = [
            PHP_BINARY,
            '-d',
            'memory_limit=' . ini_get('memory_limit'),
            // A fatal error must not end up in the message stream of the worker
            '-d',
            'display_errors=stderr',
            $entryScript,
            WarmupWorkerCommand::NAME,
            '--cached-files=' . $cachedFiles,
        ];
        if ($cacheDir !== null && $cacheDir !== '') {
            $commandLine[] = '--cache-dir=' . $cacheDir;
        }

        return $commandLine;
    }
}
