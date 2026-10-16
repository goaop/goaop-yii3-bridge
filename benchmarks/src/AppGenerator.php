<?php
/*
 * Go! AOP framework
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace Go\Yii3\GoAopBridge\Benchmarks;

use InvalidArgumentException;
use RuntimeException;

/**
 * Writes the synthetic Yii3 application measured by the benchmarks
 *
 * The application has `$services` autowired services (every even one configured by an array definition, which the
 * container reflects while it is built in debug mode) and `$aspects` aspects. Every service is advised by exactly one
 * aspect; the even aspects are listed in the bridge parameters, the odd ones tagged "goaop.aspect" in the container
 * configuration. One config directory per {@see Scenario} shares the sources.
 */
final readonly class AppGenerator
{
    public const string NAMESPACE = 'Go\\Yii3\\GoAopBridge\\Benchmarks\\App';

    public function __construct(private string $directory, private int $services, private int $aspects)
    {
        if ($services < 1) {
            throw new InvalidArgumentException('The application needs at least one service.');
        }
        if ($aspects < 1 || $aspects > $services) {
            throw new InvalidArgumentException(sprintf(
                'The number of aspects must be between 1 and the number of services (%d), %d given.',
                $services,
                $aspects,
            ));
        }
    }

    public function generate(): void
    {
        Filesystem::remove($this->directory);
        $this->write('src/Service/Counter.php', $this->counter());
        for ($i = 0; $i < $this->services; $i++) {
            $this->write("src/Service/Service{$i}.php", $this->service($i));
        }
        for ($j = 0; $j < $this->aspects; $j++) {
            $this->write("src/Aspect/Aspect{$j}.php", $this->aspect($j));
        }
        $this->write('manifest.php', $this->export([
            'services' => array_map($this->serviceClass(...), range(0, $this->services - 1)),
            'counter'  => self::NAMESPACE . '\\Service\\Counter',
            'aspects'  => $this->aspects,
        ]));
        foreach (Scenario::cases() as $scenario) {
            $directory = 'config/' . $scenario->value;
            $this->write($directory . '/.merge-plan.php', $this->mergePlan($scenario));
            $this->write($directory . '/params.php', $this->params($scenario));
            $this->write($directory . '/di.php', $this->definitions($scenario));
        }
    }

    private function serviceClass(int $index): string
    {
        return self::NAMESPACE . '\\Service\\Service' . $index;
    }

    private function aspectClass(int $index): string
    {
        return self::NAMESPACE . '\\Aspect\\Aspect' . $index;
    }

    private function counter(): string
    {
        return $this->php(<<<'PHP'
            namespace Go\Yii3\GoAopBridge\Benchmarks\App\Service;

            use Go\Yii3\GoAopBridge\Benchmarks\Contract\CallCounter;

            /**
             * Shared by every service and aspect: counts the calls and the advices that ran
             */
            final class Counter implements CallCounter
            {
                public private(set) int $calls = 0;

                public private(set) int $advices = 0;

                public function call(): void
                {
                    $this->calls++;
                }

                /**
                 * A method: an aspect is a lazy proxy, PHP refuses to modify state through its readonly properties
                 */
                public function advise(): void
                {
                    $this->advices++;
                }
            }
            PHP);
    }

    private function service(int $index): string
    {
        return $this->php(<<<PHP
            namespace Go\\Yii3\\GoAopBridge\\Benchmarks\\App\\Service;

            use Go\\Yii3\\GoAopBridge\\Benchmarks\\Contract\\Handler;

            final class Service{$index} implements Handler
            {
                public function __construct(private readonly Counter \$counter, private readonly int \$factor = 1) {}

                public function handle(int \$value): int
                {
                    \$this->counter->call();

                    return \$value * \$this->factor + {$index};
                }
            }
            PHP);
    }

    private function aspect(int $index): string
    {
        $advices = '';
        for ($i = $index; $i < $this->services; $i += $this->aspects) {
            $pointcut = sprintf('execution(public %s->handle(*))', $this->serviceClass($i));
            $advices .= <<<PHP

                    #[Before('{$pointcut}')]
                    public function beforeService{$i}(MethodInvocation \$invocation): void
                    {
                        \$this->counter->advise();
                    }

                PHP;
        }

        return $this->php(<<<PHP
            namespace Go\\Yii3\\GoAopBridge\\Benchmarks\\App\\Aspect;

            use Go\\Aop\\Aspect;
            use Go\\Aop\\Intercept\\MethodInvocation;
            use Go\\Lang\\Attribute\\Before;
            use Go\\Yii3\\GoAopBridge\\Benchmarks\\App\\Service\\Counter;

            /**
             * Its state (the counter) makes it a native lazy proxy: it is resolved from the Yii container on first use
             */
            final readonly class Aspect{$index} implements Aspect
            {
                public function __construct(private Counter \$counter) {}
            {$advices}}
            PHP);
    }

    private function mergePlan(Scenario $scenario): string
    {
        // The bridge is not installed in vendor/: its package config is listed under the vendor override layer ("//")
        $bridge = static fn(string $file): array => $scenario->usesBridge() ? ['//' => ['../config/' . $file]] : [];

        return $this->export(['/' => [
            'params' => ['yiisoft/aliases' => ['config/params.php'], ...$bridge('params.php'), '/' => ['params.php']],
            'params-console' => [
                'yiisoft/yii-console' => ['config/params-console.php'],
                'yiisoft/yii-event' => ['?config/params-console.php'],
                ...$bridge('params-console.php'),
                '/' => ['$params'],
            ],
            'di' => [
                'yiisoft/aliases' => ['config/di.php'],
                'yiisoft/yii-event' => ['?config/di.php'],
                ...$bridge('di.php'),
                '/' => ['di.php'],
            ],
            'di-console' => [
                'yiisoft/yii-console' => ['config/di-console.php'],
                'yiisoft/yii-event' => ['?config/di-console.php'],
                '/' => ['$di'],
            ],
            'events' => [],
            'events-console' => ['yiisoft/yii-console' => ['config/events-console.php'], '/' => ['$events']],
            'bootstrap' => $bridge('bootstrap.php'),
            'bootstrap-console' => ['/' => ['$bootstrap']],
        ]]);
    }

    private function params(Scenario $scenario): string
    {
        $aspects = '';
        if ($scenario === Scenario::Aspects) {
            for ($j = 0; $j < $this->aspects; $j += 2) {
                $aspects .= "\n            \\" . $this->aspectClass($j) . '::class,';
            }
            $aspects .= "\n        ";
        }
        $bridge = !$scenario->usesBridge() ? '' : <<<PHP

                'goaop/goaop-yii3-bridge' => [
                    'debug' => getenv('BENCH_DEBUG') === '1',
                    'excludePaths' => ['@src/Aspect'],
                    // Even aspects are listed here, odd ones are tagged "goaop.aspect" in di.php
                    'aspects' => [{$aspects}],
                ],
            PHP;

        return $this->php(<<<PHP
            return [
                'yiisoft/aliases' => [
                    'aliases' => [
                        '@root' => dirname(__DIR__, 2),
                        '@src' => '@root/src',
                        '@runtime' => getenv('BENCH_RUNTIME') ?: '@root/runtime',
                    ],
                ],{$bridge}
            ];
            PHP);
    }

    private function definitions(Scenario $scenario): string
    {
        $definitions = '';
        for ($i = 0; $i < $this->services; $i += 2) {
            $definitions .= sprintf("\n    \\%s::class => ['__construct()' => ['factor' => %d]],", $this->serviceClass($i), $i);
        }
        if ($scenario === Scenario::Aspects) {
            for ($j = 1; $j < $this->aspects; $j += 2) {
                $definitions .= sprintf("\n    \\%s::class => ['tags' => ['goaop.aspect']],", $this->aspectClass($j));
            }
        }

        return $this->php("return [\n    \\Psr\\Log\\LoggerInterface::class => \\Psr\\Log\\NullLogger::class,{$definitions}\n];");
    }

    /**
     * @param array<mixed> $value
     */
    private function export(array $value): string
    {
        return $this->php('return ' . var_export($value, true) . ';');
    }

    private function php(string $code): string
    {
        return "<?php\n\ndeclare(strict_types=1);\n\n// Generated by benchmarks/run.php, do not edit\n\n{$code}\n";
    }

    private function write(string $file, string $content): void
    {
        $path = $this->directory . '/' . $file;
        Filesystem::ensureDirectory(dirname($path));
        if (file_put_contents($path, $content) === false) {
            throw new RuntimeException(sprintf('Can not write "%s".', $path));
        }
    }
}
