<?php
/*
 * Go! AOP framework
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace Go\Yii3\GoAopBridge\Benchmarks\Contract;

/**
 * Counter service of the generated benchmark application
 */
interface CallCounter
{
    /**
     * Calls of the services
     */
    public int $calls { get; }

    /**
     * Advices that ran
     */
    public int $advices { get; }
}
