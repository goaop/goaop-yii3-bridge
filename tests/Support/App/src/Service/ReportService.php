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
 * Service receiving the woven repository through constructor injection
 */
final readonly class ReportService
{
    public function __construct(private ReportRepository $repository) {}

    /**
     * @return array<string, mixed>
     */
    public function publish(string $title): array
    {
        return $this->repository->create(['title' => $title]);
    }
}
