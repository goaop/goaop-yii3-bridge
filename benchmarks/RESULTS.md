# Benchmark results

Recorded with `composer benchmark` (defaults: 50 services, 10 aspects), see [README.md](README.md) for the method.
Re-run it after a change of the bridge or of its dependencies, and update this file together with
[`budgets.php`](budgets.php) when the numbers move.

## Summary

- **Production, opcache hot, no aspects: +0.45 ms per request** (median; the synthetic baseline boots in 1.62 ms).
  It is the entry line (0.53 ms) and the bootstrap callback (0.04 ms); the container build and the calls of
  unadvised services cost the same as without the bridge. Since the entry line reads the config of the runner, it
  also builds the parameters the runner used to build itself: about 0.2 ms moved from the container build into the
  entry line, the overhead did not change (see [Entry line from the config of the runner](#entry-line-from-the-config-of-the-runner)). The request includes 24 more files (the bridge, the aspect
  kernel and its class loader), its memory peak does not change, and it never loads the weaver or a parser.
- **Registering aspects is almost free.** With 1, 10 or 50 aspects the entry line stays between 0.53 and 0.65 ms
  over the runs (a few µs per aspect at most, below the noise) because aspects are only registered as deferred
  services: no aspect class is loaded while the application boots in production.
- **Resolving an aspect costs about 15 µs, once per request, on the first call of one of its advices** (the native
  lazy proxy is initialized from the Yii container). An advised call then costs about 0.5 µs more than a plain call
  (5,000 advised calls: 2.85 ms instead of 0.40 ms), which is the interception of the framework.
- **Debug mode adds +0.87 ms without aspects.** With woven classes it costs about 70 µs per woven class and request:
  in debug mode the framework includes woven classes through `php://filter` (so breakpoints and magic constants point
  at the original sources), and opcache never caches those includes. That is the price of debug mode, keep it off in
  production.
- **Without opcache** (console commands), the bridge adds about +5 ms per process without aspects, mostly compiling
  its files and the kernel. The first request after a deployment without `aop:warmup` (`cli-cold`) weaves every class
  it loads: +45 ms without aspects, +112 ms with them for the 50 classes of this application. Run `./yii aop:warmup`
  at deployment.

Every budget of `budgets.php` is met, and the structural rules hold in every mode (see [README.md](README.md)).

## Where the entry line goes

`AopBootstrap::init()` in production with a hot opcache (10 aspects, medians of 200 requests of a profiling copy of
the entry line):

| Step | Time |
|---|--:|
| `Config` of the application, merged `params` group (now the config of the runner, which reuses the group) | 0.13 ms |
| `KernelOptions` and `AspectList` from the parameters | 0.03 ms |
| tagged aspects: merged `di` and `di-tags` groups, tag lookup | 0.06 ms |
| aspect kernel initialization (framework services, class loader, cache index) | 0.13 ms |

The `di` group is merged by yiisoft/config for the tag lookup, and again by the runner when it builds the container.
The merge grows with the size of the container configuration: with 1,000 more array definitions the tag lookup took
0.73 ms (0.63 ms of it merging the group, while the runner needed 1.33 ms to build the container). Applications with
a large container configuration and no tagged aspects can set `'aspectTag' => null` to skip it.

## Entry line from the config of the runner

`AopBootstrap::init()` reads the config of the runner instead of a `Config` of its own (issue #3), and records the
classes autoloaded meanwhile. A/B on 2026-10-19, same machine and session, one run after the other
(`php benchmarks/run.php --modes=server-hot --requests=1000`, production mode, medians in ms). That machine was slower
than the one of the full results below (baseline 2.2 ms instead of 1.6 ms), compare the columns, not the files:

| Scenario | Overhead before | Overhead after | Entry line before | after | Container before | after | Bootstrap before | after |
|---|--:|--:|--:|--:|--:|--:|--:|--:|
| empty | +0.77 | +0.65 | 0.76 | 0.92 | 0.58 | 0.34 | 0.08 | 0.11 |
| aspects | +4.48 | +4.52 | 0.85 | 1.03 | 0.59 | 0.37 | 0.07 | 0.11 |

The parameters are built once, in the entry line, and the container build no longer builds them: the overhead stays
the same within the noise, the `entryLine` budgets were raised by that share (0.25 ms). A request loads 2 more files
(`config/kernel.php`, `EarlyLoadedClasses`). The full results below predate the change.

## Aspect count (opcache hot, 50 services)

| Aspects | Mode | Entry line | Bootstrap | First calls | Steady calls | Total | Overhead |
|--:|---|--:|--:|--:|--:|--:|--:|
| 1 | production | 0.57 | 0.05 | 0.75 | 2.88 | 4.90 | +3.30 |
| 10 | production | 0.57 | 0.05 | 0.91 | 2.85 | 5.03 | +3.41 |
| 50 | production | 0.55 | 0.05 | 1.44 | 2.86 | 5.52 | +4.04 |
| 1 | debug | 0.64 | 0.10 | 2.60 | 2.92 | 9.09 | +6.95 |
| 10 | debug | 0.70 | 0.12 | 2.90 | 2.98 | 9.69 | +7.51 |
| 50 | debug | 0.88 | 0.24 | 4.65 | 2.87 | 11.72 | +9.64 |

In debug mode the bootstrap callback loads every aspect class to check it (0.24 ms for 50 aspects). Every aspect
scenario weaves the same 50 services: the steady calls do not depend on the number of aspects.

## Opcache JIT

With `opcache.jit=tracing` the hot requests measure about the same as without the JIT (production, no aspects: +0.47 ms;
with aspects: +3.75 ms). On the PHP 8.5.11 build used here, weaving with the tracing JIT enabled fails: once the boot
of the application has warmed up the JIT, the parser of pointcuts rejects valid pointcuts (`Unexpected token`).
It does not happen with `opcache.jit=disable` (the PHP default) or `function`, nor on requests served from the
AOP cache. Build the cache with the JIT disabled (`php -d opcache.jit=disable ./yii aop:warmup`, the CLI runs without
opcache by default) if your PHP build is affected.

## Full results

| | |
|---|---|
| date | 2026-10-16 19:44 UTC |
| php | 8.5.11 (Linux x86_64, NTS) |
| cpu | Intel(R) Xeon(R) Processor @ 2.80GHz (4 logical cores) |
| application | 50 services (every even one array-defined), 10 aspects advising them (100 steady calls per service) |
| samples | 30 processes per CLI mode, 500 requests (after 50 warm-up requests) per server, opcache.jit=disable |
| goaop/framework | dev-master (02cbe01d) |
| yiisoft/config | 1.6.2 (a760058c) |
| yiisoft/di | 1.4.1 (09545283) |
| yiisoft/yii-runner-console | 2.2.1 (16ca4daa) |

Medians in milliseconds; "overhead" compares the median total with the one of the baseline (no bridge).

#### CLI, cold AOP cache, no opcache, production mode

| Scenario | Total | p95 | Overhead | Entry line | Container | Bootstrap | First calls | Steady calls | Memory peak | Files |
|---|--:|--:|--:|--:|--:|--:|--:|--:|--:|--:|
| baseline | 14.80 | 20.61 | – | – | 6.31 | 0.06 | 2.46 | 0.44 | 2.6 MiB | 126 |
| empty | 60.09 | 76.99 | +45.29 (+306%) | 6.99 | 5.12 | 0.38 | 39.90 | 0.45 | 7.4 MiB | 246 |
| aspects | 127.07 | 170.04 | +112.27 (+758%) | 6.39 | 4.72 | 0.35 | 107.20 | 3.03 | 10.3 MiB | 402 |

#### CLI, cold AOP cache, no opcache, debug mode

| Scenario | Total | p95 | Overhead | Entry line | Container | Bootstrap | First calls | Steady calls | Memory peak | Files |
|---|--:|--:|--:|--:|--:|--:|--:|--:|--:|--:|
| baseline | 17.29 | 24.75 | – | – | 8.91 | 0.07 | 1.67 | 0.45 | 2.9 MiB | 133 |
| empty | 60.83 | 80.63 | +43.54 (+252%) | 6.24 | 35.85 | 0.40 | 11.04 | 0.50 | 7.6 MiB | 252 |
| aspects | 144.33 | 192.70 | +127.04 (+735%) | 6.53 | 92.02 | 0.43 | 36.10 | 3.18 | 10.4 MiB | 408 |

#### CLI, warm AOP cache, no opcache, production mode

| Scenario | Total | p95 | Overhead | Entry line | Container | Bootstrap | First calls | Steady calls | Memory peak | Files |
|---|--:|--:|--:|--:|--:|--:|--:|--:|--:|--:|
| baseline | 15.14 | 22.65 | – | – | 6.35 | 0.05 | 2.35 | 0.42 | 2.6 MiB | 126 |
| empty | 20.15 | 27.43 | +5.01 (+33%) | 6.35 | 4.94 | 0.33 | 2.32 | 0.41 | 3.2 MiB | 150 |
| aspects | 28.59 | 40.13 | +13.46 (+89%) | 6.60 | 4.81 | 0.34 | 6.54 | 2.99 | 4.0 MiB | 231 |

#### CLI, warm AOP cache, no opcache, debug mode

| Scenario | Total | p95 | Overhead | Entry line | Container | Bootstrap | First calls | Steady calls | Memory peak | Files |
|---|--:|--:|--:|--:|--:|--:|--:|--:|--:|--:|
| baseline | 18.54 | 25.25 | – | – | 9.49 | 0.06 | 1.84 | 0.50 | 2.9 MiB | 133 |
| empty | 23.80 | 31.06 | +5.27 (+28%) | 6.44 | 7.92 | 0.35 | 1.74 | 0.53 | 3.6 MiB | 157 |
| aspects | 32.99 | 45.95 | +14.45 (+78%) | 6.56 | 10.73 | 0.58 | 5.54 | 3.09 | 4.2 MiB | 242 |

#### php -S, opcache + AOP cache hot, production mode

| Scenario | Total | p95 | Overhead | Entry line | Container | Bootstrap | First calls | Steady calls | Memory peak | Files |
|---|--:|--:|--:|--:|--:|--:|--:|--:|--:|--:|
| baseline | 1.62 | 2.56 | – | – | 0.48 | 0.01 | 0.53 | 0.40 | 0.5 MiB | 126 |
| empty | 2.07 | 3.19 | +0.45 (+28%) | 0.53 | 0.42 | 0.05 | 0.50 | 0.40 | 0.5 MiB | 150 |
| aspects | 5.03 | 9.37 | +3.41 (+211%) | 0.57 | 0.43 | 0.05 | 0.90 | 2.85 | 0.7 MiB | 231 |

#### php -S, opcache + AOP cache hot, debug mode

| Scenario | Total | p95 | Overhead | Entry line | Container | Bootstrap | First calls | Steady calls | Memory peak | Files |
|---|--:|--:|--:|--:|--:|--:|--:|--:|--:|--:|
| baseline | 2.18 | 3.43 | – | – | 0.95 | 0.02 | 0.50 | 0.45 | 0.5 MiB | 133 |
| empty | 3.05 | 4.74 | +0.87 (+40%) | 0.66 | 0.99 | 0.07 | 0.63 | 0.45 | 0.5 MiB | 157 |
| aspects | 9.69 | 15.92 | +7.51 (+344%) | 0.70 | 2.57 | 0.12 | 2.90 | 2.98 | 0.9 MiB | 242 |
