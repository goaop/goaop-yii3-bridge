<?php
/*
 * Go! AOP framework
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace Go\Yii3\GoAopBridge\Tests\App\Service;

use RuntimeException;

/**
 * Autowired service (no container definition), woven by GreetingAspect
 */
class GreetingService
{
    public function greet(string $name): string
    {
        return "Hello, {$name}!";
    }

    public function shout(string $text): string
    {
        return $text;
    }

    public function explode(): never
    {
        throw new RuntimeException('boom');
    }
}
