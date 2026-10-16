<?php
/*
 * Go! AOP framework
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace Go\Yii3\GoAopBridge\Tests\Unit\Config;

use Go\Yii3\GoAopBridge\Config\KernelOptions;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yiisoft\Aliases\Aliases;

final class KernelOptionsTest extends TestCase
{
    private Aliases $aliases;

    protected function setUp(): void
    {
        $this->aliases = new Aliases([
            '@root'    => '/app',
            '@src'     => '@root/src',
            '@runtime' => '@root/runtime',
        ]);
    }

    protected function tearDown(): void
    {
        foreach (KernelOptions::DEBUG_ENVIRONMENT_VARIABLES as $variable) {
            putenv($variable);
            unset($_ENV[$variable]);
        }
    }

    public function testPackageDefaultsResolveYiiAliases(): void
    {
        $params = require dirname(__DIR__, 3) . '/config/params.php';
        $this->assertIsArray($params);
        $this->assertIsArray($params[KernelOptions::PARAMS_KEY]);

        $options = KernelOptions::fromParams($params[KernelOptions::PARAMS_KEY], $this->aliases);

        $this->assertFalse($options->debug);
        $this->assertSame('/app', $options->appDir);
        $this->assertSame('/app/runtime/aop', $options->cacheDir);
        $this->assertNull($options->cacheFileMode);
        $this->assertSame(0, $options->features);
        $this->assertSame(['/app/src'], $options->includePaths);
        $this->assertSame([], $options->excludePaths);
    }

    public function testToArrayMatchesKernelOptionsFormat(): void
    {
        $options = KernelOptions::fromParams([
            'debug'         => true,
            'appDir'        => '@root',
            'cacheDir'      => '/tmp/aop',
            'cacheFileMode' => 0750,
            'features'      => 4,
            'includePaths'  => ['@src/Service'],
            'excludePaths'  => ['@src/Service/Legacy', '/opt/shared'],
        ], $this->aliases);

        $this->assertSame([
            'debug'         => true,
            'appDir'        => '/app',
            'cacheDir'      => '/tmp/aop',
            'features'      => 4,
            'includePaths'  => ['/app/src/Service'],
            'excludePaths'  => ['/app/src/Service/Legacy', '/opt/shared'],
            'cacheFileMode' => 0750,
        ], $options->toArray());
    }

    public function testToArrayLeavesCacheFileModeToTheKernelWhenNotConfigured(): void
    {
        $options = KernelOptions::fromParams(['appDir' => '@root', 'cacheDir' => '@runtime/aop'], $this->aliases);

        $this->assertArrayNotHasKey('cacheFileMode', $options->toArray());
    }

    /**
     * @return iterable<string, array{mixed, int}>
     */
    public static function cacheFileModes(): iterable
    {
        yield 'integer' => [0770, 0770];
        yield 'octal digits' => ['770', 0770];
        yield 'octal digits with leading zero' => ['0750', 0750];
    }

    #[DataProvider('cacheFileModes')]
    public function testCacheFileModeAcceptsIntegersAndOctalStrings(mixed $value, int $expected): void
    {
        $options = KernelOptions::fromParams(
            ['appDir' => '@root', 'cacheDir' => '/tmp/aop', 'cacheFileMode' => $value],
            $this->aliases,
        );

        $this->assertSame($expected, $options->cacheFileMode);
    }

    /**
     * @return iterable<string, array{mixed, bool}>
     */
    public static function debugValues(): iterable
    {
        yield 'boolean' => [true, true];
        yield 'string true' => ['true', true];
        yield 'string 0' => ['0', false];
        yield 'string off' => ['off', false];
        yield 'integer 1' => [1, true];
    }

    #[DataProvider('debugValues')]
    public function testDebugAcceptsBooleanLikeValues(mixed $value, bool $expected): void
    {
        $options = KernelOptions::fromParams(
            ['appDir' => '@root', 'cacheDir' => '/tmp/aop', 'debug' => $value],
            $this->aliases,
        );

        $this->assertSame($expected, $options->debug);
    }

    public function testNullDebugFollowsAppDebugEnvironmentVariable(): void
    {
        putenv('APP_DEBUG=true');

        $options = KernelOptions::fromParams(['appDir' => '@root', 'cacheDir' => '/tmp/aop'], $this->aliases);

        $this->assertTrue($options->debug);
    }

    public function testGoAopDebugEnvironmentVariableTakesPrecedenceOverAppDebug(): void
    {
        putenv('APP_DEBUG=true');
        $_ENV['GOAOP_DEBUG'] = 'false';

        $options = KernelOptions::fromParams(['appDir' => '@root', 'cacheDir' => '/tmp/aop'], $this->aliases);

        $this->assertFalse($options->debug);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidParams(): iterable
    {
        $valid = ['appDir' => '@root', 'cacheDir' => '/tmp/aop'];

        yield 'missing cache directory' => [['appDir' => '@root'], 'parameter "cacheDir" must be a non-empty path string, null given'];
        yield 'empty application directory' => [['appDir' => ''] + $valid, 'parameter "appDir" must be a non-empty path string, string given'];
        yield 'debug array' => [['debug' => []] + $valid, 'parameter "debug" must be a boolean'];
        yield 'debug word' => [['debug' => 'sometimes'] + $valid, 'parameter "debug" must be a boolean'];
        yield 'cache file mode digits' => [['cacheFileMode' => '999'] + $valid, 'parameter "cacheFileMode" must be an integer'];
        yield 'features string' => [['features' => '4'] + $valid, 'parameter "features" must be an integer bitmask'];
        yield 'include paths string' => [['includePaths' => '@src'] + $valid, 'parameter "includePaths" must be a list of paths, string given'];
        yield 'exclude path integer' => [['excludePaths' => [42]] + $valid, 'parameter "excludePaths" must be a list of non-empty path strings, int given'];
    }

    /**
     * @param array<string, mixed> $params
     */
    #[DataProvider('invalidParams')]
    public function testInvalidParameterFailsWithItsName(array $params, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "goaop/goaop-yii3-bridge" ' . $message);

        KernelOptions::fromParams($params, $this->aliases);
    }

    public function testUnknownAliasIsReported(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid path alias: @unknown');

        KernelOptions::fromParams(['appDir' => '@root', 'cacheDir' => '@unknown/aop'], $this->aliases);
    }
}
