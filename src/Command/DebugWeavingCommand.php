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
 * The framework\'s "debug:weaving" command, named "aop:debug:weaving", that reports that AOP is disabled instead of failing when
 * the application runs without the aspect kernel
 */
#[AsCommand(name: 'aop:debug:weaving', description: 'Checks consistency in weaving process')]
final class DebugWeavingCommand extends FrameworkDebugWeavingCommand
{
    use ReportsDisabledAop;
}
