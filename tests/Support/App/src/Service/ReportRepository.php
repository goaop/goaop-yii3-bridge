<?php
/*
 * Go! AOP framework
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace Go\Yii3\GoAopBridge\Tests\App\Service;

/**
 * Repository advised by ReportAspect, constructor-injected into ReportService
 */
class ReportRepository
{
    /**
     * @param array<string, mixed> $report
     *
     * @return array<string, mixed>
     */
    public function create(array $report): array
    {
        return $report + ['id' => 42];
    }
}
