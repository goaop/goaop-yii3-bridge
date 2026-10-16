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
 * Configurations of the benchmark application compared with each other
 */
enum Scenario: string
{
    /**
     * The application without the bridge: no entry line, no bridge config
     */
    case Baseline = 'baseline';

    /**
     * The bridge installed and initialized from the entry line, without any aspect
     */
    case Empty = 'empty';

    /**
     * The bridge with aspects advising every service
     */
    case Aspects = 'aspects';

    public function usesBridge(): bool
    {
        return $this !== self::Baseline;
    }
}
