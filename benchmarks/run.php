<?php

declare(strict_types=1);

/*
 * Measures the boot time of a Yii3 application with and without the bridge, see benchmarks/README.md
 */

use Go\Yii3\GoAopBridge\Benchmarks\Benchmark;
use Go\Yii3\GoAopBridge\Benchmarks\Budget;
use Go\Yii3\GoAopBridge\Benchmarks\Filesystem;
use Go\Yii3\GoAopBridge\Benchmarks\Options;
use Go\Yii3\GoAopBridge\Benchmarks\Report;

$rootPath = dirname(__DIR__);
require $rootPath . '/vendor/autoload.php';

$arguments = array_values(array_filter(
    array_slice(is_array($_SERVER['argv'] ?? null) ? $_SERVER['argv'] : [], 1),
    is_string(...),
));
if (Options::wantsHelp($arguments)) {
    echo Options::USAGE;
    exit(0);
}

try {
    $options = Options::fromArguments($arguments, __DIR__ . '/var/results');
} catch (InvalidArgumentException $e) {
    fwrite(STDERR, $e->getMessage() . "\n\n" . Options::USAGE);
    exit(2);
}

$log       = static function (string $message): void {
    fwrite(STDERR, '[' . date('H:i:s') . '] ' . $message . "\n");
};
$benchmark = new Benchmark($options, $rootPath, __DIR__ . '/var', $log(...));
$results   = $benchmark->run();
$report    = new Report($options, $results, Report::environment($options, $rootPath));

Filesystem::ensureDirectory($options->output);
file_put_contents($options->output . '/results.md', $report->markdown());
file_put_contents(
    $options->output . '/results.json',
    json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n",
);
echo $report->markdown();
$log('Results written to ' . $options->output);

/** @var list<array{mode: string, debug: bool, scenario: string, metric: string, max: float}> $budgets */
$budgets    = require __DIR__ . '/budgets.php';
$violations = (new Budget($budgets))->check($options, $results);
foreach ($violations as $violation) {
    fwrite(STDERR, ($options->assert ? 'FAIL ' : 'WARN ') . $violation . "\n");
}
if ($violations === []) {
    $log('Every budget of benchmarks/budgets.php is met.');
}
exit($options->assert && $violations !== [] ? 1 : 0);
