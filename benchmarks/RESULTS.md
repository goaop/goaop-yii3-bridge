# Benchmark results

Recorded with `composer benchmark` (defaults: 50 services, 10 aspects), see [README.md](README.md) for the method.
Re-run it after a change of the bridge or of its dependencies, and update this file together with
[`budgets.php`](budgets.php) when the numbers move.

## Summary

Recorded on 2026-11-07 with goaop/framework 4.0.0 on PHP 8.5.12, on a faster machine than the previous results (the
baseline boots in 1.02 ms instead of 1.62 ms): the absolute numbers are lower, relative to the baseline the overheads
of the bridge are the same as with the framework `dev-master` of the previous results. A second full run and the
aspect count runs below agree within 0.05 ms on the hot requests.

- **Production, opcache hot, no aspects: +0.31 ms per request** (median; the synthetic baseline boots in 1.02 ms).
  It is the entry line (0.37 ms) and the bootstrap callback (0.04 ms); the container build is cheaper than without
  the bridge, because the runner reuses the parameters the entry line built, and the calls of unadvised services cost
  the same. The request includes 27 more files (the bridge, its config, the aspect kernel and its class loader), its
  memory peak does not change, and it never loads the weaver or a parser.
- **Registering aspects is almost free.** With 1, 10 or 50 aspects the entry line stays between 0.45 and 0.51 ms (a
  few µs per aspect at most) because aspects are only registered as deferred services: no aspect class is loaded
  while the application boots in production.
- **Resolving an aspect costs about 10 µs, once per request, on the first call of one of its advices** (the native
  lazy proxy is initialized from the Yii container). An advised call then costs about 0.4 µs more than a plain call
  (5,000 advised calls: 2.16 ms instead of 0.30 ms), which is the interception of the framework.
- **Debug mode adds +0.51 ms without aspects.** With woven classes it costs about 35 µs per woven class and request:
  in debug mode the framework includes woven classes through `php://filter` (so breakpoints and magic constants point
  at the original sources), and opcache never caches those includes. That is the price of debug mode, keep it off in
  production.
- **Without opcache** (console commands), the bridge adds about +4.5 ms per process without aspects, mostly compiling
  its files and the kernel. The first request after a deployment without `aop:warmup` (`cli-cold`) weaves every class
  it loads: +33 ms without aspects, +108 ms with them for the 50 classes of this application. Run `./yii aop:warmup`
  at deployment.

Every budget of `budgets.php` is met, and the structural rules hold in every mode (see [README.md](README.md)). The
budgets are unchanged: they were set to about twice the medians of the slower machine of the previous results, which
is the headroom CI machines need.

## Where the entry line goes

`AopBootstrap::init()` in production with a hot opcache (10 aspects, medians of 200 requests of a profiling copy of
the entry line; measured on 2026-10-16 with the framework `dev-master` and the tag lookup enabled, not re-measured):

| Step | Time |
|---|--:|
| `Config` of the application, merged `params` group (now the config of the runner, which reuses the group) | 0.13 ms |
| `KernelOptions` and `AspectList` from the parameters | 0.03 ms |
| tagged aspects: merged `di` and `di-tags` groups, tag lookup | 0.06 ms |
| aspect kernel initialization (framework services, class loader, cache index) | 0.13 ms |

The `di` group is merged by yiisoft/config for the tag lookup, and again by the runner when it builds the container.
The merge grows with the size of the container configuration: with 1,000 more array definitions the tag lookup took
0.73 ms (0.63 ms of it merging the group, while the runner needed 1.33 ms to build the container). The tag lookup is
opt-in since then (`aspectTag` is `null` by default), so only applications enabling it pay for it.

## Entry line from the config of the runner

`AopBootstrap::init()` reads the config of the runner instead of a `Config` of its own, and the tag lookup is opt-in
(`aspectTag` defaults to `null`, issue #3). A/B on 2026-10-23, same machine and session, one run after the other
(`php benchmarks/run.php --modes=server-hot --debug=0 --requests=1000`, medians in ms). Compare the columns, not the
files: the machine differs from the one of the full results below.

| Scenario | Overhead before | Overhead after | Entry line before | after | Container before | after | Bootstrap before | after |
|---|--:|--:|--:|--:|--:|--:|--:|--:|
| empty (defaults, no tag lookup) | +0.46 | +0.30 | 0.46 | 0.43 | 0.39 | 0.20 | 0.05 | 0.04 |
| aspects (tag lookup enabled) | +3.64 | +3.04 | 0.52 | 0.53 | 0.42 | 0.22 | 0.05 | 0.04 |

The runner reuses the parameters the entry line built, so the container build got cheaper, and with its defaults the
entry line no longer evaluates the `di` and `di-tags` groups: a request without aspects costs about 0.15 ms less. A
request loads 1 more file (`config/kernel.php`). The full results below include the change.

## Aspect count (opcache hot, 50 services)

| Aspects | Mode | Entry line | Bootstrap | First calls | Steady calls | Total | Overhead |
|--:|---|--:|--:|--:|--:|--:|--:|
| 1 | production | 0.45 | 0.04 | 0.53 | 2.15 | 3.43 | +2.40 |
| 10 | production | 0.45 | 0.04 | 0.62 | 2.16 | 3.55 | +2.53 |
| 50 | production | 0.51 | 0.04 | 1.01 | 2.20 | 4.06 | +3.01 |
| 1 | debug | 0.49 | 0.06 | 1.46 | 2.18 | 5.58 | +4.27 |
| 10 | debug | 0.55 | 0.08 | 1.60 | 2.22 | 5.91 | +4.57 |
| 50 | debug | 0.69 | 0.15 | 1.95 | 2.26 | 6.69 | +5.33 |

`php benchmarks/run.php --modes=server-hot --aspects=1` (and `=50`), the 10 aspects row is the full run below. In
debug mode the bootstrap callback loads every aspect class to check it (0.15 ms for 50 aspects). Every aspect
scenario weaves the same 50 services: the steady calls do not depend on the number of aspects.

## Opcache JIT

With `opcache.jit=tracing` (`php benchmarks/run.php --modes=server-hot --debug=0 --jit=tracing`) the hot requests
measure about the same as without the JIT: production, no aspects +0.25 ms, with aspects +2.22 ms (the 5,000 advised
calls take 1.88 ms instead of 2.16 ms). These requests are served from the AOP cache, built by a CLI process without
opcache. The tracing JIT of PHP 8.5.11 and 8.5.12 has bugs of its own: on 8.5.11, once the boot of the application has
warmed up the JIT, the parser of pointcuts rejects valid pointcuts while weaving (`Unexpected token`); on 8.5.12,
processes of the test suite crash with a segmentation fault. Build the cache with the JIT disabled
(`php -d opcache.jit=disable ./yii aop:warmup`, the CLI runs without opcache by default) if your PHP build is affected.

## Full results

| | |
|---|---|
| date | 2026-11-07 11:58 UTC |
| php | 8.5.12 (Linux x86_64, NTS) |
| cpu | Intel(R) Xeon(R) Processor @ 2.10GHz (4 logical cores) |
| application | 50 services (every even one array-defined), 10 aspects advising them (100 steady calls per service) |
| samples | 30 processes per CLI mode, 500 requests (after 50 warm-up requests) per server, opcache.jit=disable |
| goaop/framework | 4.0.0 (5d524816) |
| yiisoft/config | 1.6.2 (a760058c) |
| yiisoft/di | 1.4.1 (09545283) |
| yiisoft/yii-runner-console | 2.2.1 (16ca4daa) |

Medians in milliseconds; "overhead" compares the median total with the one of the baseline (no bridge).

#### CLI, cold AOP cache, no opcache, production mode

| Scenario | Total | p95 | Overhead | Entry line | Container | Bootstrap | First calls | Steady calls | Memory peak | Files |
|---|--:|--:|--:|--:|--:|--:|--:|--:|--:|--:|
| baseline | 12.13 | 15.55 | – | – | 5.00 | 0.04 | 1.45 | 0.35 | 2.7 MiB | 128 |
| empty | 45.43 | 62.36 | +33.30 (+274%) | 7.48 | 1.86 | 0.33 | 30.41 | 0.36 | 7.6 MiB | 256 |
| aspects | 120.36 | 157.16 | +108.22 (+892%) | 7.78 | 1.76 | 0.33 | 104.04 | 2.35 | 10.6 MiB | 412 |

#### CLI, cold AOP cache, no opcache, debug mode

| Scenario | Total | p95 | Overhead | Entry line | Container | Bootstrap | First calls | Steady calls | Memory peak | Files |
|---|--:|--:|--:|--:|--:|--:|--:|--:|--:|--:|
| baseline | 13.24 | 18.57 | – | – | 6.68 | 0.04 | 1.05 | 0.37 | 2.9 MiB | 135 |
| empty | 45.92 | 65.22 | +32.68 (+247%) | 7.37 | 25.24 | 0.35 | 8.09 | 0.39 | 7.8 MiB | 262 |
| aspects | 130.86 | 149.06 | +117.61 (+888%) | 7.89 | 76.96 | 0.41 | 38.60 | 2.42 | 10.8 MiB | 418 |

#### CLI, warm AOP cache, no opcache, production mode

| Scenario | Total | p95 | Overhead | Entry line | Container | Bootstrap | First calls | Steady calls | Memory peak | Files |
|---|--:|--:|--:|--:|--:|--:|--:|--:|--:|--:|
| baseline | 11.40 | 14.16 | – | – | 4.69 | 0.04 | 1.42 | 0.34 | 2.7 MiB | 128 |
| empty | 15.91 | 21.33 | +4.50 (+39%) | 7.18 | 1.80 | 0.31 | 1.40 | 0.34 | 3.5 MiB | 156 |
| aspects | 20.49 | 26.47 | +9.09 (+80%) | 7.27 | 1.78 | 0.32 | 4.34 | 2.26 | 4.2 MiB | 238 |

#### CLI, warm AOP cache, no opcache, debug mode

| Scenario | Total | p95 | Overhead | Entry line | Container | Bootstrap | First calls | Steady calls | Memory peak | Files |
|---|--:|--:|--:|--:|--:|--:|--:|--:|--:|--:|
| baseline | 12.91 | 17.03 | – | – | 6.54 | 0.04 | 1.01 | 0.37 | 2.9 MiB | 135 |
| empty | 17.54 | 20.48 | +4.64 (+36%) | 7.10 | 3.77 | 0.34 | 1.04 | 0.34 | 3.7 MiB | 163 |
| aspects | 24.58 | 34.56 | +11.67 (+90%) | 7.44 | 5.77 | 0.52 | 3.77 | 2.27 | 4.4 MiB | 249 |

#### php -S, opcache + AOP cache hot, production mode

| Scenario | Total | p95 | Overhead | Entry line | Container | Bootstrap | First calls | Steady calls | Memory peak | Files |
|---|--:|--:|--:|--:|--:|--:|--:|--:|--:|--:|
| baseline | 1.02 | 1.56 | – | – | 0.27 | 0.01 | 0.34 | 0.29 | 0.5 MiB | 128 |
| empty | 1.33 | 1.97 | +0.31 (+30%) | 0.37 | 0.16 | 0.04 | 0.35 | 0.30 | 0.5 MiB | 155 |
| aspects | 3.55 | 5.51 | +2.53 (+248%) | 0.45 | 0.16 | 0.04 | 0.62 | 2.16 | 0.7 MiB | 237 |

#### php -S, opcache + AOP cache hot, debug mode

| Scenario | Total | p95 | Overhead | Entry line | Container | Bootstrap | First calls | Steady calls | Memory peak | Files |
|---|--:|--:|--:|--:|--:|--:|--:|--:|--:|--:|
| baseline | 1.35 | 1.83 | – | – | 0.53 | 0.01 | 0.31 | 0.34 | 0.5 MiB | 135 |
| empty | 1.86 | 2.47 | +0.51 (+38%) | 0.44 | 0.49 | 0.05 | 0.40 | 0.35 | 0.5 MiB | 162 |
| aspects | 5.91 | 8.25 | +4.57 (+339%) | 0.55 | 1.28 | 0.08 | 1.60 | 2.22 | 0.9 MiB | 248 |
