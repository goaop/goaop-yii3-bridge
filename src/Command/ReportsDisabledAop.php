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
use Go\Yii3\GoAopBridge\Config\KernelOptions;
use ReflectionClass;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * An "aop:*" command of an application that runs without AOP ("enabled" false) says so and succeeds, instead of
 * failing on the kernel that was never initialized
 *
 * A command given the "loader" argument boots a kernel of its own and runs as usual. A command that wraps a framework
 * command under a name of the "aop" namespace takes the help of the framework command, which a subclass does not
 * inherit (#[AsCommand] is read from the class itself).
 */
trait ReportsDisabledAop
{
    private KernelOptions $kernelOptions;

    public function __construct(KernelOptions $kernelOptions)
    {
        $this->kernelOptions = $kernelOptions;
        parent::__construct();
        if ($this->getHelp() === '') {
            foreach ((new ReflectionClass(parent::class))->getAttributes(AsCommand::class) as $attribute) {
                $this->setHelp($attribute->newInstance()->help ?? '');
            }
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->reportsDisabledAop($input, $output) ? Command::SUCCESS : parent::execute($input, $output);
    }

    /**
     * Writes that AOP is disabled when it is, and returns whether it did
     */
    private function reportsDisabledAop(InputInterface $input, OutputInterface $output): bool
    {
        if (AspectKernel::isInitialized() || $this->kernelOptions->enabled
            || ($input->hasArgument('loader') && $input->getArgument('loader') !== null)) {
            return false;
        }
        $output->writeln('<comment>' . KernelOptions::DISABLED_MESSAGE . '</comment>');

        return true;
    }
}
