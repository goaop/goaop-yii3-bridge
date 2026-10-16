<?php
/*
 * Go! AOP framework
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace Go\Yii3\GoAopBridge\Tests\App\Command;

use Go\Yii3\GoAopBridge\Tests\App\Service\GreetingService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Application command calling a woven service
 */
#[AsCommand(name: 'app:greet', description: 'Shouts a text through the woven greeting service')]
final class GreetCommand extends Command
{
    public function __construct(private readonly GreetingService $greetingService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('text', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $text = $input->getArgument('text');
        $output->writeln($this->greetingService->shout(is_string($text) ? $text : ''));

        return Command::SUCCESS;
    }
}
