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

use Go\Aop\Exception\InvalidConfigurationException;
use Go\Console\Command\OpcacheWarmupCommand as FrameworkOpcacheWarmupCommand;
use Go\Instrument\ClassLoading\AopComposerLoader;
use Go\Yii3\GoAopBridge\Config\ConfigFiles;
use Go\Yii3\GoAopBridge\Config\KernelOptions;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Yiisoft\Aliases\Aliases;

/**
 * Primes the opcache file cache with the AOP cache and the application, the framework's "cache:warmup:opcache" with
 * the files of the Yii3 application as default entry points
 *
 * Every Yii3 request also includes its configuration, which no class map covers: the merge plan of the runner and
 * every file it lists, of every group and environment (application and vendor packages), and the entry scripts. They
 * are compiled like the --entry files, which are then only needed for other files. The merge plan is the one the
 * runner of this process has read (yiisoft/config requires it when the config is created), its directory is the
 * config directory; the files of the packages lie below the vendor directory of Composer.
 */
#[AsCommand(
    name: 'aop:opcache',
    description: 'Prime the opcache file cache with the AOP cache and the application',
    help: <<<'EOT'
Compiles into the opcache file cache (<info>opcache.file_cache</info>) what a production request loads: the AOP
cache (woven classes, proxies, advisor caches and metadata), Composer's class map and autoloaded files, the sources
below the include paths that are not woven, the configuration of the application and the entry points. A new
PHP-FPM master or CLI process then loads compiled scripts from disk instead of compiling them.

The configuration is the merge plan of yiisoft/config and every file it lists, of every group and environment,
the application's and the vendor packages'. The entry points are <info>public/index.php</info>, this entry script
and the files given with <comment>--entry</comment>, for anything else a request includes.

Compiled scripts are written to disk only by a process started with the file cache settings, which can not be
changed at runtime:

  <info>mkdir -p /var/cache/opcache && php -d opcache.enable_cli=1 -d opcache.file_cache_only=1 \
      -d opcache.file_cache=/var/cache/opcache ./yii %command.name% --file-cache=/var/cache/opcache</info>

<comment>--file-cache=DIR</comment> checks that the process writes to that directory. The file cache belongs to the
PHP build and to the absolute paths of the files: run the command in the final image, after <info>aop:warmup</info>,
with the PHP binary that serves the requests.
EOT,
)]
final class OpcacheWarmupCommand extends FrameworkOpcacheWarmupCommand
{
    use ReportsDisabledAop {
        ReportsDisabledAop::__construct as private initializeReportsDisabledAop;
    }

    public function __construct(private readonly Aliases $aliases, KernelOptions $kernelOptions)
    {
        $this->initializeReportsDisabledAop($kernelOptions);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->reportsDisabledAop($input, $output)) {
            return self::SUCCESS;
        }
        $entries = $input->getOption('entry');
        $entries = [...(is_array($entries) ? $entries : []), ...$this->applicationFiles()];
        // A file of the application also given with --entry is compiled once; an entry that does not resolve is left
        // to the framework command, which reports it
        $uniqueEntries = [];
        foreach ($entries as $entry) {
            $path = is_string($entry) ? realpath($entry) : false;
            $uniqueEntries[$path === false ? 'unresolved:' . count($uniqueEntries) : $path] = $entry;
        }
        $input->setOption('entry', array_values($uniqueEntries));

        return parent::execute($input, $output);
    }

    /**
     * Returns the existing files of the application to compile besides the classes
     *
     * @return list<string>
     */
    private function applicationFiles(): array
    {
        $files    = [];
        $rootPath = $this->aliases->get('@root');
        foreach ([$this->alias('@public', $rootPath . '/public') . '/index.php', $rootPath . '/yii', $_SERVER['SCRIPT_FILENAME'] ?? null] as $file) {
            if (is_string($file) && is_file($file)) {
                $files[] = $file;
            }
        }
        $vendorDir = $this->vendorDir();
        foreach (ConfigFiles::loadedMergePlans() as $mergePlan) {
            $files = [...$files, ...ConfigFiles::ofMergePlan($mergePlan, $vendorDir)];
        }

        return $files;
    }

    /**
     * Returns the vendor directory of Composer, the one yiisoft/config resolves the files of the packages in
     */
    private function vendorDir(): string
    {
        try {
            return AopComposerLoader::ofProcess()->vendorDir;
        } catch (InvalidConfigurationException) {
            return $this->alias('@vendor', $this->aliases->get('@root') . '/vendor');
        }
    }

    /**
     * Resolves an alias of the application templates, the given path when the application does not define it
     */
    private function alias(string $alias, string $default): string
    {
        $path = $this->aliases->getAll()[$alias] ?? null;

        return is_string($path) ? $path : $default;
    }
}
