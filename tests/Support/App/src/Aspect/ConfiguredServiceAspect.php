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
 * Aspect advising the service that has an array definition
 */
final class ConfiguredServiceAspect implements Aspect
{
    #[Around('execution(public Go\Yii3\GoAopBridge\Tests\App\Service\ConfiguredService->describe(*))')]
    public function aroundDescribe(MethodInvocation $invocation): string
    {
        $description = $invocation->proceed();

        return 'woven ' . (is_string($description) ? $description : '');
    }
}
