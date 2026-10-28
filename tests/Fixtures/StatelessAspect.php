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
 * Aspect without instance properties, created by a factory of the Yii container: PHP can not make it lazy
 */
final class StatelessAspect implements Aspect
{
    /**
     * Calls of the factory in the container definition
     */
    public static int $factoryCalls = 0;

    /**
     * The instance the advice ran on
     */
    public static ?self $advisedBy = null;

    #[Around('execution(public Go\Yii3\GoAopBridge\Tests\App\Service\ConfiguredService->describe(*))')]
    public function aroundDescribe(MethodInvocation $invocation): string
    {
        self::$advisedBy = $this;
        $description     = $invocation->proceed();

        return 'stateless ' . (is_string($description) ? $description : '');
    }
}
