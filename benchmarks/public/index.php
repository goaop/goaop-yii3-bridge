<?php

declare(strict_types=1);

/*
 * Front controller of the benchmark application: the CLI runs execute it as a script, the standalone server
 * (php -S) as its router. It starts the application the way the entry scripts of a Yii3 application do (the bridge
 * entry line, then the runner builds the container and runs the "bootstrap" group), runs a small workload and prints
 * the duration of every phase as JSON.
 *
 * The container is built by the console runner: the HTTP runner is not a dependency of the bridge, and the work of
 * the bridge (entry line, aspect registration, bootstrap callback, weaving) does not depend on the runner.
 *
 * Environment: BENCH_SCENARIO (baseline|empty|aspects), BENCH_DEBUG (0|1), BENCH_APP (generated application),
 * BENCH_RUNTIME ("@runtime" alias), BENCH_ITERATIONS (calls of every service in the steady phase).
 */

use Composer\Autoload\ClassLoader;
use Go\Yii3\GoAopBridge\AopBootstrap;
use Go\Yii3\GoAopBridge\Benchmarks\Contract\CallCounter;
use Go\Yii3\GoAopBridge\Benchmarks\Contract\Handler;
use Yiisoft\Yii\Runner\BootstrapRunner;
use Yiisoft\Yii\Runner\Console\ConsoleApplicationRunner;

$marks    = ['start' => hrtime(true)];
$rootPath = dirname(__DIR__, 2);
$loader   = require $rootPath . '/vendor/autoload.php';
$marks['autoload'] = hrtime(true);
if (!$loader instanceof ClassLoader) {
    throw new UnexpectedValueException('vendor/autoload.php must return the composer class loader.');
}

$scenario        = (string) getenv('BENCH_SCENARIO');
$debug           = getenv('BENCH_DEBUG') === '1';
$appDirectory    = (string) getenv('BENCH_APP');
$configDirectory = substr($appDirectory, strlen($rootPath) + 1) . '/config/' . $scenario;
// The generated application is not known to composer.json; registered before the kernel wraps the loader
$loader->addPsr4('Go\\Yii3\\GoAopBridge\\Benchmarks\\App\\', $appDirectory . '/src');

$runner = new ConsoleApplicationRunner(rootPath: $rootPath, debug: $debug, configDirectory: $configDirectory);

// The entry line, as in the entry scripts of the application: between the creation of the runner and its run
if ($scenario !== 'baseline') {
    AopBootstrap::init($runner->getConfig(), $rootPath);
}
$marks['entryLine'] = hrtime(true);

$container = $runner->getContainer();
$marks['container'] = hrtime(true);

$bootstrap = $runner->getConfig()->get('bootstrap-console');
(new BootstrapRunner($container, $bootstrap))->run();
$marks['bootstrap'] = hrtime(true);

$aspectNamespace = 'Go\\Yii3\\GoAopBridge\\Benchmarks\\App\\Aspect\\';
$aspectClasses   = static fn(): int => count(array_filter(
    get_declared_classes(),
    static fn(string $class): bool => str_starts_with($class, $aspectNamespace),
));
// The probe is not part of the measured phases
$probeStart             = hrtime(true);
$aspectClassesAfterBoot = $aspectClasses();
$excluded               = ['firstCalls' => hrtime(true) - $probeStart];

/** @var array{services: list<string>, counter: string, aspects: int} $manifest */
$manifest = require $appDirectory . '/manifest.php';

// First call of every service: loads the (woven) classes, resolves the services and the aspects advising them
$services = [];
$checksum = 0;
foreach ($manifest['services'] as $id) {
    $service = $container->get($id);
    if (!$service instanceof Handler) {
        throw new UnexpectedValueException(sprintf('Service "%s" can not handle calls.', $id));
    }
    $checksum += $service->handle(1);
    $services[] = $service;
}
$marks['firstCalls'] = hrtime(true);

// Steady state: the advices of resolved aspects only
$iterations = max(0, (int) getenv('BENCH_ITERATIONS'));
for ($k = 0; $k < $iterations; $k++) {
    foreach ($services as $service) {
        $checksum += $service->handle($k);
    }
}
$marks['steadyCalls'] = hrtime(true);

$counter = $container->get($manifest['counter']);
if (!$counter instanceof CallCounter) {
    throw new UnexpectedValueException('The counter service is missing.');
}

$phases = [];
$names  = array_keys($marks);
for ($i = 1, $count = count($names); $i < $count; $i++) {
    $phases[$names[$i]] = ($marks[$names[$i]] - $marks[$names[$i - 1]] - ($excluded[$names[$i]] ?? 0)) / 1e6;
}
$phases['total'] = array_sum($phases);

$opcache = function_exists('opcache_get_status') ? opcache_get_status(false) : false;

$result = [
    'scenario' => $scenario,
    'debug' => $debug,
    'sapi' => PHP_SAPI,
    'phases' => $phases,
    'memoryPeak' => memory_get_peak_usage(),
    'includedFiles' => count(get_included_files()),
    'opcache' => is_array($opcache) && ($opcache['opcache_enabled'] ?? false) === true,
    // Subsystems of the framework a warm production request must never need
    'loaded' => [
        'kernel' => class_exists(Go\Core\AspectKernel::class, false),
        'phpParser' => class_exists(PhpParser\ParserAbstract::class, false),
        'pointcutParser' => class_exists(Go\Aop\Pointcut\PointcutParser::class, false),
        'weaver' => class_exists(Go\Instrument\Transformer\WeavingTransformer::class, false),
        // Aspects are loaded lazily in production: none while the application boots
        'aspectClassesAfterBoot' => $aspectClassesAfterBoot,
        'aspectClasses' => $aspectClasses(),
    ],
    'calls' => $counter->calls,
    'advices' => $counter->advices,
    'checksum' => $checksum,
];

if (PHP_SAPI !== 'cli') {
    header('Content-Type: application/json');
}
echo json_encode($result, JSON_THROW_ON_ERROR), "\n";
