<?php
/*
 * Go! AOP framework
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace Go\Yii3\GoAopBridge\Tests\Unit\Config;

use Go\Yii3\GoAopBridge\Config\ConfigFiles;
use PHPUnit\Framework\TestCase;

final class ConfigFilesTest extends TestCase
{
    private const string FIXTURE = __DIR__ . '/../../Fixtures/MergePlan';

    public function testEveryGroupAndEnvironmentOfTheMergePlanIsListed(): void
    {
        $fixture = (string) realpath(self::FIXTURE);

        $files = ConfigFiles::ofMergePlan($fixture . '/config/.merge-plan.php', $fixture . '/vendor');

        $this->assertSame([
            $fixture . '/config/.merge-plan.php',
            $fixture . '/vendor/acme/shop/config/params.php',
            $fixture . '/vendor/acme/shop/config/override.php',
            $fixture . '/config/common/aliases.php',
            $fixture . '/config/common/params.php',
            $fixture . '/config/web.php',
            $fixture . '/config/environments/prod/params.php',
        ], $files, 'Group references and missing optional files are left out, patterns are expanded');
    }

    public function testMergePlansReadByThisProcessAreFound(): void
    {
        $mergePlan = (string) realpath(self::FIXTURE . '/config/.merge-plan.php');
        require $mergePlan;

        $this->assertContains($mergePlan, ConfigFiles::loadedMergePlans());
    }
}
