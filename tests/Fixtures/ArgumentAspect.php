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

/**
 * Aspect without instance properties whose constructor requires an argument: PHP can not make it lazy
 */
final class ArgumentAspect implements Aspect
{
    /** @var list<string> */
    public static array $labels = [];

    public function __construct(string $label)
    {
        self::$labels[] = $label;
    }
}
