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

use Go\Aop\Exception\InvalidConfigurationException;
use Go\Core\AspectKernel;
use Go\Instrument\ClassLoading\Warmup\WarmupWorker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Worker process of a parallel "aop:warmup --workers=N", started by that command through the entry script
 *
 * Weaves the files the coordinating process sends to its standard input and answers each of them on its standard
 * output with the outcome and the cache records it produced. The entry line of the worker process initialized the
 * kernel with the configuration and the aspects of the application, as in the coordinator, so the worker loop runs on
 * that kernel: the loop boots its kernel by including a "loader" file, and the file of this class, included for sure,
 * makes that a no-op.
 *
 * @internal
 */
#[AsCommand(
    name: self::NAME,
    description: 'Worker process of aop:warmup, started by that command',
    hidden: true,
)]
final class WarmupWorkerCommand extends Command
{
    public const string NAME = 'aop:warmup-worker';

    public function __construct(private readonly AspectKernel $aspectKernel)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('cached-files', null, InputOption::VALUE_REQUIRED, 'Number of parsed files kept in memory');
        $this->addOption('cache-dir', null, InputOption::VALUE_REQUIRED, 'Directory the cache is built in');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cachedFiles = $input->getOption('cached-files');
        if ($cachedFiles !== null && (!is_string($cachedFiles) || !ctype_digit($cachedFiles) || (int) $cachedFiles < 1)) {
            throw InvalidConfigurationException::optionNotPositiveInteger('cached-files', is_string($cachedFiles) ? $cachedFiles : get_debug_type($cachedFiles));
        }
        $cacheDir = $input->getOption('cache-dir');

        return WarmupWorker::main(
            __FILE__,
            $cachedFiles === null ? $this->aspectKernel->getOptions()['parsedFilesLimit'] : (int) $cachedFiles,
            null,
            null,
            is_string($cacheDir) && $cacheDir !== '' ? $cacheDir : null,
        );
    }
}
