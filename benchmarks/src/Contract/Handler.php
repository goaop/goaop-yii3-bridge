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
 * Implemented by every service of the generated benchmark application
 */
interface Handler
{
    public function handle(int $value): int;
}
