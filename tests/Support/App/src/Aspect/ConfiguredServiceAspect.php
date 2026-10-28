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
 *
 * In debug mode the service is woven while the container is built: the aspect needs an instance property to be
 * lazy, it is resolved from the container later, on the first call of its advice.
 */
final class ConfiguredServiceAspect implements Aspect
{
    public function __construct(private readonly string $marker = 'woven') {}

    #[Around('execution(public Go\Yii3\GoAopBridge\Tests\App\Service\ConfiguredService->describe(*))')]
    public function aroundDescribe(MethodInvocation $invocation): string
    {
        $description = $invocation->proceed();

        return $this->marker . ' ' . (is_string($description) ? $description : '');
    }
}
