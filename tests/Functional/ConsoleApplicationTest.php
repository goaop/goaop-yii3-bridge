<?php
/*
 * Go! AOP framework
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace Go\Yii3\GoAopBridge\Tests\Functional;

use Go\Yii3\GoAopBridge\Tests\Support\TestApplication;
use PHPUnit\Framework\TestCase;

/**
 * Runs the console entry script of the fixture application (tests/Support/App/yii) in its own PHP process
 */
final class ConsoleApplicationTest extends TestCase
{
    private string $runtime;

    protected function setUp(): void
    {
        $this->runtime = sys_get_temp_dir() . '/goaop-yii3-bridge-tests/functional-' . bin2hex(random_bytes(4));
        mkdir($this->runtime, 0777, true);
    }

    public function testApplicationCommandRunsWovenService(): void
    {
        [$exitCode, $output] = $this->yii(['app:greet', 'hello']);

        $this->assertSame(0, $exitCode, $output);
        $this->assertSame('HELLO!', trim($output));
    }

    public function testBootstrapFallbackWeavesWithoutTheEntryLine(): void
    {
        [$exitCode, $output] = $this->yii(['app:greet', 'fallback'], entryLine: false);

        $this->assertSame(0, $exitCode, $output);
        $this->assertSame('FALLBACK!', trim($output));
    }

    public function testCommandsAreListedInTheAopNamespace(): void
    {
        [$exitCode, $output] = $this->yii(['list', 'aop']);

        $this->assertSame(0, $exitCode, $output);
        foreach (['aop:warmup', 'aop:debug:aspect', 'aop:debug:advisor', 'aop:debug:weaving'] as $command) {
            $this->assertStringContainsString($command, $output);
        }
    }

    public function testWarmupPreparesTheCacheForProduction(): void
    {
        [$exitCode, $output] = $this->yii(['aop:warmup']);

        $this->assertSame(0, $exitCode, $output);
        $this->assertStringContainsString('Total processed', $output);
        $this->assertFileExists($this->runtime . '/aop/src/Service/GreetingService.php');
        $this->assertFileExists($this->runtime . '/aop/src/Service/GreetingServiceOriginalTrait.php');
    }

    /**
     * @param list<string> $arguments
     *
     * @return array{int, string}
     */
    private function yii(array $arguments, bool $entryLine = true): array
    {
        $command = [PHP_BINARY, TestApplication::rootPath() . '/tests/Support/App/yii', ...$arguments];
        $env     = [
            'GOAOP_TEST_RUNTIME'    => $this->runtime,
            'GOAOP_TEST_ENTRY_LINE' => $entryLine ? '1' : '0',
            'PATH'                  => (string) getenv('PATH'),
        ];

        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
        $this->assertIsResource($process);
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $output];
    }
}
