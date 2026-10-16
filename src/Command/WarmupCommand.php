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
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Weaves every class below the include paths into the cache directory ahead of time, e.g. during deployment
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
EOT,
)]
final class WarmupCommand extends FrameworkCacheWarmupCommand
{
    use InjectedKernel;
}
