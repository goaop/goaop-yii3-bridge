<?php
/*
 * Go! AOP framework
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace Go\Yii3\GoAopBridge\Tests\Fixtures;

use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Attribute\Around;

/**
 * Aspect whose creation runs its own advice: its container definition calls the method it advises
 */
final readonly class CircularAspect implements Aspect
{
    public function __construct(private string $label) {}

    #[Around('execution(public Go\Yii3\GoAopBridge\Tests\App\Service\ConfiguredService->describe(*))')]
    public function aroundDescribe(MethodInvocation $invocation): string
    {
        $description = $invocation->proceed();

        return $this->label . ' ' . (is_string($description) ? $description : '');
    }
}
