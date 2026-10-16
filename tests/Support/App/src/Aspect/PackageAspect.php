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
use Go\Lang\Attribute\Before;
use Go\Yii3\GoAopBridge\Tests\App\Service\Journal;

/**
 * Aspect contributed by a third-party package through its own params
 */
final readonly class PackageAspect implements Aspect
{
    public function __construct(private Journal $journal) {}

    #[Before('execution(public Go\Yii3\GoAopBridge\Tests\App\Service\GreetingService->greet(*))')]
    public function beforeGreet(MethodInvocation $invocation): void
    {
        $this->journal->record('package:' . $invocation->getMethod()->getName());
    }
}
