<?php
/*
 * Go! AOP framework
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace Go\Yii3\GoAopBridge\Tests\App\Aspect;

use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Attribute\Around;

/**
 * Aspect whose scalar constructor argument comes from an array definition of the Yii container
 */
final readonly class ReportAspect implements Aspect
{
    public function __construct(private string $label) {}

    /**
     * @return array<mixed>
     */
    #[Around('execution(public Go\Yii3\GoAopBridge\Tests\App\Service\ReportRepository->create(*))')]
    public function aroundCreate(MethodInvocation $invocation): array
    {
        $report = $invocation->proceed();

        return (is_array($report) ? $report : []) + [$this->label => true];
    }
}
