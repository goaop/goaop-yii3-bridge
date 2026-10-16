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
use Go\Lang\Attribute\After;
use Go\Lang\Attribute\AfterThrowing;
use Go\Lang\Attribute\Around;
use Go\Lang\Attribute\Before;
use Go\Yii3\GoAopBridge\Tests\App\Service\Journal;

/**
 * Aspect with a dependency autowired by the Yii container
 */
final class GreetingAspect implements Aspect
{
    /**
     * Number of instances created, proves that the aspect is resolved lazily
     */
    public static int $instances = 0;

    public function __construct(private readonly Journal $journal)
    {
        self::$instances++;
    }

    #[Before('execution(public Go\Yii3\GoAopBridge\Tests\App\Service\GreetingService->greet(*))')]
    public function beforeGreet(MethodInvocation $invocation): void
    {
        $this->journal->record('before:' . $invocation->getMethod()->getName());
    }

    #[After('execution(public Go\Yii3\GoAopBridge\Tests\App\Service\GreetingService->greet(*))')]
    public function afterGreet(MethodInvocation $invocation): void
    {
        $this->journal->record('after:' . $invocation->getMethod()->getName());
    }

    #[Around('execution(public Go\Yii3\GoAopBridge\Tests\App\Service\GreetingService->shout(*))')]
    public function aroundShout(MethodInvocation $invocation): mixed
    {
        $text = $invocation->proceed();

        return strtoupper(is_string($text) ? $text : '') . '!';
    }

    #[AfterThrowing('execution(public Go\Yii3\GoAopBridge\Tests\App\Service\GreetingService->explode(*))')]
    public function afterExplode(MethodInvocation $invocation): void
    {
        $this->journal->record('afterThrowing:' . $invocation->getMethod()->getName());
    }
}
