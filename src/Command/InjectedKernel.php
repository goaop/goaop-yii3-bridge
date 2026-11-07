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

use Go\Core\AspectKernel;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Adapts a framework command extending {@see \Go\Console\Command\BaseAspectCommand} to the Yii container
 *
 * The framework commands take the kernel of the process, or execute a "loader" file given as an optional argument to
 * boot one. In a Yii3 application the entry line boots the kernel and the container provides it, so it is injected
 * and the argument, which would execute a file of the application once more, is dropped.
 *
 * @internal
 */
trait InjectedKernel
{
    public function __construct(AspectKernel $aspectKernel)
    {
        $this->aspectKernel = $aspectKernel;

        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();

        $definition = $this->getDefinition();
        $definition->setArguments(array_values(array_filter(
            $definition->getArguments(),
            static fn(InputArgument $argument): bool => $argument->getName() !== 'loader',
        )));
    }

    /**
     * The kernel is injected by the container, there is nothing to load
     */
    protected function loadAspectKernel(InputInterface $input, OutputInterface $output): void {}
}
