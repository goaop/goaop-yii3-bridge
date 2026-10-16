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

use Go\Console\Command\DebugWeavingCommand as FrameworkDebugWeavingCommand;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Weaves the application twice and reports proxies that differ between the passes
 */
#[AsCommand(
    name: 'aop:debug:weaving',
    description: 'Checks the consistency of the weaving process',
    help: <<<'EOT'
Weaves the whole application twice and compares the generated proxies: a proxy that only appears, or looks
different, on the second pass reveals circular references and mutual dependencies between woven classes and
aspects (for example an aspect that depends on a class it advises).

  <info>%command.full_name%</info>
  <info>%command.full_name% -v</info>   also prints the difference of each unstable proxy

The cache directory of the kernel is rewritten.
EOT,
)]
final class DebugWeavingCommand extends FrameworkDebugWeavingCommand
{
    use InjectedKernel;
}
