<?php
/*
 * Go! AOP framework
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace Go\Yii3\GoAopBridge\Tests\Config;

use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Yii3\GoAopBridge\Command\DebugAdvisorCommand;
use Go\Yii3\GoAopBridge\Command\DebugAspectCommand;
use Go\Yii3\GoAopBridge\Command\DebugWeavingCommand;
use Go\Yii3\GoAopBridge\Command\WarmupCommand;
use Go\Yii3\GoAopBridge\Config\AspectList;
use Go\Yii3\GoAopBridge\Config\KernelOptions;
use Go\Yii3\GoAopBridge\Kernel\AspectYii3Kernel;
use Go\Yii3\GoAopBridge\Tests\App\Aspect\ConfiguredServiceAspect;
use Go\Yii3\GoAopBridge\Tests\App\Aspect\GreetingAspect;
use Go\Yii3\GoAopBridge\Tests\App\Aspect\ReportAspect;
use Go\Yii3\GoAopBridge\Tests\App\Service\Journal;
use Go\Yii3\GoAopBridge\Tests\Support\TestApplication;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Config\ConfigInterface;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;

/**
 * Loads the config files of the package the way yiisoft/config does (with $params in scope), into a validating
 * container, without the entry line: the "bootstrap" callback initializes the kernel itself
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class PackageConfigTest extends TestCase
{
    private const string CONFIG_DIR = __DIR__ . '/../../config';

    public function testComposerRegistersTheConfigGroups(): void
    {
        $composer = json_decode((string) file_get_contents(__DIR__ . '/../../composer.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($composer);
        $this->assertIsArray($composer['extra']);

        $this->assertSame(['source-directory' => 'config'], $composer['extra']['config-plugin-options']);
        $groups = $composer['extra']['config-plugin'];
        $this->assertIsArray($groups);
        $this->assertSame(['params', 'di', 'bootstrap', 'params-console', 'goaop-kernel'], array_keys($groups));
        foreach ($groups as $file) {
            $this->assertIsString($file);
            $this->assertFileExists(self::CONFIG_DIR . '/' . $file);
        }
    }

    public function testBootstrapInitializesKernelFromContainerOptions(): void
    {
        $container = self::bootstrap(self::container());

        $kernel = $container->get(AspectYii3Kernel::class);
        $this->assertInstanceOf(AspectYii3Kernel::class, $kernel);
        $this->assertTrue($kernel->isInitialized());
        $this->assertTrue($kernel->isContainerAttached());
        $this->assertSame($kernel, $container->get(AspectKernel::class));
        $this->assertSame($kernel->getContainer(), $container->get(AspectContainer::class));
        $this->assertTrue($kernel->getContainer()->has(GreetingAspect::class));

        $options = $kernel->getOptions();
        $this->assertTrue($options['debug']);
        $this->assertSame(TestApplication::prepareRuntime() . '/aop', $options['cacheDir']);
    }

    public function testKernelOptionsAndAspectsAreContainerServices(): void
    {
        $container = self::container();

        $options = $container->get(KernelOptions::class);
        $this->assertInstanceOf(KernelOptions::class, $options);
        $this->assertSame(dirname(__DIR__, 2), $options->appDir);
        $this->assertSame([dirname(__DIR__, 2) . '/src'], $options->includePaths);

        $aspects = $container->get(AspectList::class);
        $this->assertInstanceOf(AspectList::class, $aspects);
        $this->assertSame([GreetingAspect::class], $aspects->toArray());
    }

    public function testBootstrapRegistersAspectsTaggedInTheRunnerConfig(): void
    {
        // The runners register their config as ConfigInterface
        $config = new readonly class implements ConfigInterface {
            /**
             * @return array<mixed>
             */
            public function get(string $group): array
            {
                return match ($group) {
                    'di'      => [ReportAspect::class => ['__construct()' => ['label' => 'audited'], 'tags' => ['goaop.aspect']]],
                    'di-tags' => ['goaop.aspect' => [ConfiguredServiceAspect::class]],
                    default   => throw new LogicException(sprintf('No "%s" config group', $group)),
                };
            }

            public function has(string $group): bool
            {
                return in_array($group, ['di', 'di-tags'], true);
            }
        };

        $container = self::bootstrap(self::container(definitions: [ConfigInterface::class => $config]));

        $kernel = $container->get(AspectYii3Kernel::class);
        $this->assertInstanceOf(AspectYii3Kernel::class, $kernel);
        $this->assertSame(
            [GreetingAspect::class, ConfiguredServiceAspect::class, ReportAspect::class],
            $kernel->getAspects()->toArray(),
        );
        $this->assertTrue($kernel->getContainer()->has(ReportAspect::class));
    }

    public function testBootstrapKeepsTheKernelInitializedByTheEntryScript(): void
    {
        $runtime = TestApplication::prepareRuntime();
        $kernel  = AspectYii3Kernel::instance();
        $kernel->boot(
            new KernelOptions(false, dirname(__DIR__, 2), $runtime, null, 0, [], []),
            AspectList::fromClassNames([GreetingAspect::class]),
        );

        self::bootstrap(self::container(definitions: [
            KernelOptions::class => static fn(): never => throw new LogicException('The options must not be resolved'),
            AspectList::class    => static fn(): never => throw new LogicException('The aspects must not be resolved'),
        ]));

        $this->assertTrue($kernel->isContainerAttached());
        $this->assertSame($runtime, $kernel->getOptions()['cacheDir']);
    }

    public function testDebugModeReportsConfiguredClassThatIsNotAnAspect(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Class "' . Journal::class . '" listed in the "goaop/goaop-yii3-bridge" parameter "aspects" must implement the "Go\Aop\Aspect" interface.');

        self::bootstrap(self::container([Journal::class]));
    }

    public function testKernelGroupReturnsTheParametersOfItsConfig(): void
    {
        $bridge  = ['debug' => false];
        $aliases = ['aliases' => ['@root' => '/app']];

        $this->assertSame(
            ['goaop/goaop-yii3-bridge' => $bridge, 'yiisoft/aliases' => $aliases],
            self::requireConfig('kernel.php', ['goaop/goaop-yii3-bridge' => $bridge, 'yiisoft/aliases' => $aliases, 'app' => []]),
        );
        $this->assertSame(
            ['goaop/goaop-yii3-bridge' => null, 'yiisoft/aliases' => null],
            self::requireConfig('kernel.php', []),
        );
    }

    public function testConsoleParamsRegisterTheCommands(): void
    {
        $params = self::requireConfig('params-console.php', []);
        $this->assertIsArray($params['yiisoft/yii-console']);

        $this->assertSame([
            'aop:warmup'        => WarmupCommand::class,
            'aop:debug:aspect'  => DebugAspectCommand::class,
            'aop:debug:advisor' => DebugAdvisorCommand::class,
            'aop:debug:weaving' => DebugWeavingCommand::class,
        ], $params['yiisoft/yii-console']['commands']);
    }

    /**
     * @param list<string>         $aspects
     * @param array<string, mixed> $definitions Definitions replacing the ones of the package
     */
    private static function container(array $aspects = [GreetingAspect::class], array $definitions = []): ContainerInterface
    {
        $runtime = TestApplication::prepareRuntime();
        $params  = array_replace_recursive(self::requireConfig('params.php', []), [
            'goaop/goaop-yii3-bridge' => [
                'debug'        => true,
                'excludePaths' => ['@src/Aspect'],
                'aspects'      => $aspects,
            ],
        ]);

        $definitions += self::requireConfig('di.php', $params) + [
            Aliases::class => new Aliases([
                '@root'    => dirname(__DIR__, 2),
                '@src'     => '@root/src',
                '@runtime' => $runtime,
            ]),
        ];

        return new Container(ContainerConfig::create()->withDefinitions($definitions)->withValidate(true));
    }

    private static function bootstrap(ContainerInterface $container): ContainerInterface
    {
        foreach (self::requireConfig('bootstrap.php', []) as $callback) {
            self::assertIsCallable($callback);
            $callback($container);
        }

        return $container;
    }

    /**
     * @param array<mixed> $params Made visible to the config file, as yiisoft/config does
     *
     * @return array<mixed>
     */
    private static function requireConfig(string $file, array $params): array
    {
        $config = require self::CONFIG_DIR . '/' . $file;
        self::assertIsArray($config);

        return $config;
    }
}
