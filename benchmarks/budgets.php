<?php

declare(strict_types=1);

/*
 * Timing budgets checked by benchmarks/run.php, "--assert" fails the run when one is exceeded.
 *
 * Every budget applies to the median of its measurement (milliseconds; "overhead" is the median total minus the one
 * of the baseline application without the bridge). The limits leave about twice the medians recorded in
 * benchmarks/RESULTS.md, to absorb slower and noisier CI machines; tighten them when a change makes the bridge faster.
 */
return [
    // Production, opcache hot: what every web request pays
    ['mode' => 'server-hot', 'debug' => false, 'scenario' => 'empty', 'metric' => 'overhead', 'max' => 1.0],
    // The entry line builds the parameters the runner reuses (RESULTS.md, entry line from the config of the runner)
    ['mode' => 'server-hot', 'debug' => false, 'scenario' => 'empty', 'metric' => 'entryLine', 'max' => 1.25],
    ['mode' => 'server-hot', 'debug' => false, 'scenario' => 'aspects', 'metric' => 'entryLine', 'max' => 1.5],
    ['mode' => 'server-hot', 'debug' => false, 'scenario' => 'aspects', 'metric' => 'bootstrap', 'max' => 0.25],
    ['mode' => 'server-hot', 'debug' => false, 'scenario' => 'aspects', 'metric' => 'firstCalls', 'max' => 2.0],

    // Debug mode, opcache hot: development requests
    ['mode' => 'server-hot', 'debug' => true, 'scenario' => 'empty', 'metric' => 'overhead', 'max' => 2.0],
    ['mode' => 'server-hot', 'debug' => true, 'scenario' => 'aspects', 'metric' => 'overhead', 'max' => 16.0],

    // Console commands (no opcache), AOP cache warm
    ['mode' => 'cli-warm', 'debug' => false, 'scenario' => 'empty', 'metric' => 'overhead', 'max' => 10.0],
    ['mode' => 'cli-warm', 'debug' => true, 'scenario' => 'empty', 'metric' => 'overhead', 'max' => 12.0],
];
