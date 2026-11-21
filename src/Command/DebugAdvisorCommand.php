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
 * The framework\'s "debug:advisor" command, named "aop:debug:advisor", that reports that AOP is disabled instead of failing when
 * the application runs without the aspect kernel
 */
#[AsCommand(name: 'aop:debug:advisor', description: 'Provides an interface for checking and debugging advisors')]
final class DebugAdvisorCommand extends FrameworkDebugAdvisorCommand
{
    use ReportsDisabledAop;
}
