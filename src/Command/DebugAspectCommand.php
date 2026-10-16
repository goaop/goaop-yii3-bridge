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

use Go\Console\Command\DebugAspectCommand as FrameworkDebugAspectCommand;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Lists the aspects of the application with the pointcuts and advisors they declare
 */
#[AsCommand(
    name: 'aop:debug:aspect',
    description: 'Shows the registered aspects with their pointcuts and advisors',
    help: <<<'EOT'
Lists the aspects registered in the aspect kernel, with the pointcuts and advisors declared by each of them.

  <info>%command.full_name%</info>
  <info>%command.full_name% --aspect='App\Aspect\LoggingAspect'</info>

With <comment>--aspect</comment> only that aspect is shown; an unknown aspect is reported as an error.
EOT,
)]
final class DebugAspectCommand extends FrameworkDebugAspectCommand
{
    use InjectedKernel;
}
