<?php
/*
 * Go! AOP framework
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace Go\Yii3\GoAopBridge\Benchmarks;

/**
 * How the front controller is executed
 */
enum Mode: string
{
    /**
     * A PHP process per sample without opcache, the AOP cache is emptied before every sample (first deployment)
     */
    case CliCold = 'cli-cold';

    /**
     * A PHP process per sample without opcache, the AOP cache filled (console commands)
     */
    case CliWarm = 'cli-warm';

    /**
     * Requests to the standalone server (php -S) with opcache, both the opcache and the AOP cache hot
     */
    case ServerHot = 'server-hot';

    public function label(): string
    {
        return match ($this) {
            self::CliCold => 'CLI, cold AOP cache, no opcache',
            self::CliWarm => 'CLI, warm AOP cache, no opcache',
            self::ServerHot => 'php -S, opcache + AOP cache hot',
        };
    }
}
