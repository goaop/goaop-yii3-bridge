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

use Go\Console\Command\DebugAdvisorCommand as FrameworkDebugAdvisorCommand;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Lists the advisors of the application, or the classes and members one advisor matches
 */
#[AsCommand(
    name: 'aop:debug:advisor',
    description: 'Shows the advisors and the joinpoints they match',
    help: <<<'EOT'
Lists the advisors registered in the aspect kernel, or shows which classes and members one advisor matches.

  <info>%command.full_name%</info>
  <info>%command.full_name% --advisor='App\Aspect\LoggingAspect->beforeMethod'</info>

With <comment>--advisor</comment> every file below the include paths is analysed; an unknown advisor id is
reported as an error.
EOT,
)]
final class DebugAdvisorCommand extends FrameworkDebugAdvisorCommand
{
    use InjectedKernel;
}
