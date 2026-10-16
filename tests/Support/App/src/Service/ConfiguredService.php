<?php
/*
 * Go! AOP framework
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace Go\Yii3\GoAopBridge\Tests\App\Service;

/**
 * Service with an array definition: in debug mode the container autoloads it while it is built
 */
class ConfiguredService
{
    public function __construct(private readonly string $prefix) {}

    public function describe(): string
    {
        return $this->prefix;
    }
}
