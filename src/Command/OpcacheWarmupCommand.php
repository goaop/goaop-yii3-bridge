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

use Go\Console\Command\OpcacheWarmupCommand as FrameworkOpcacheWarmupCommand;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Primes the opcache file cache with the AOP cache and the application, after "aop:warmup"
 */
#[AsCommand(
    name: 'aop:opcache',
    description: 'Primes the opcache file cache with the AOP cache and the application',
    help: <<<'EOT'
Compiles into the opcache file cache (<info>opcache.file_cache</info>) what a production request loads: the AOP
cache (woven classes, proxies, advisor caches and metadata), Composer's class map and autoloaded files, the sources
below the include paths that are not woven and the entry points given with <info>--entry</info>. A new PHP-FPM
master or CLI process then loads compiled scripts from disk instead of compiling them.

Compiled scripts are written to disk only by a process started with the file cache settings, which can not be
changed at runtime:

  <info>mkdir -p /var/cache/opcache && php -d opcache.enable_cli=1 -d opcache.file_cache_only=1 \
      -d opcache.file_cache=/var/cache/opcache \
      ./yii %command.name% --file-cache=/var/cache/opcache --entry=public/index.php --entry=yii</info>

<info>--file-cache=DIR</info> checks that the process writes to that directory; without it the configured directory
is used. The file cache belongs to the PHP build and to the absolute paths of the files: run the command in the
final image, after <info>aop:warmup</info>, with the PHP binary that serves the requests.

The AOP cache and the entry points must compile; files of the Composer class map that do not (vendor test stubs
written for a newer PHP) are listed and skipped. <info>-v</info> lists every file.
EOT,
)]
final class OpcacheWarmupCommand extends FrameworkOpcacheWarmupCommand
{
    use InjectedKernel;
}
