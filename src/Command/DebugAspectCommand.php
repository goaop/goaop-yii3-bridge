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
 * The framework\'s "debug:aspect" command, named "aop:debug:aspect", that reports that AOP is disabled instead of failing when
 * the application runs without the aspect kernel
 */
#[AsCommand(name: 'aop:debug:aspect', description: 'Provides an interface for querying the information about aspects')]
final class DebugAspectCommand extends FrameworkDebugAspectCommand
{
    use ReportsDisabledAop;
}
