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
 * The framework commands load the kernel by executing a "loader" file given as an argument. In a Yii3 application
 * the kernel is a container service, so it is injected instead and the argument is dropped.
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
