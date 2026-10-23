# Benchmarks

Measures how much the bridge adds to the boot of a Yii3 application, and what aspects cost, in production and in
debug mode. The recorded results and their analysis are in [RESULTS.md](RESULTS.md).

```bash
composer benchmark                       # full matrix, about a minute
composer benchmark -- --quick --assert   # smoke run, fails when a budget is exceeded
php benchmarks/run.php --help
```

The run writes `benchmarks/var/results/results.md` (the tables printed at the end) and `results.json` (every
statistic, with the environment). `benchmarks/var/` is generated and ignored by git.

## What is measured

`run.php` generates a synthetic application in `benchmarks/var/app`: `--services` autowired services (every even one
configured by an array definition, which the container reflects while it is built in debug mode), each advised by one
of `--aspects` aspects with a `#[Before]` advice. Half of the aspects are listed in the bridge parameters, the other
half tagged `goaop.aspect` in the container configuration (the `aspects` scenario enables the opt-in tag lookup),
and every aspect depends on a service (so it is a lazy proxy resolved from the Yii container).

[`public/index.php`](public/index.php) is the front controller. It starts the application the way the entry scripts
of a Yii3 application do and times every phase with `hrtime()`:

| Phase | What happens |
|---|---|
| `autoload` | `vendor/autoload.php` |
| `entryLine` | the runner is created, then `AopBootstrap::init()` reads its config: params of the runner, tagged aspects when the lookup is enabled, aspect kernel (bridge scenarios only) |
| `container` | the runner builds the container (`ConsoleApplicationRunner::getContainer()`) |
| `bootstrap` | the `bootstrap` config group, with the bridge callback attaching the container to the kernel |
| `firstCalls` | first call of every service: (woven) classes loaded, services and aspects resolved |
| `steadyCalls` | `--iterations` more calls of every service: the advices alone |

The container is built by the console runner because the HTTP runner is no dependency of the bridge; the work of the
bridge does not depend on the runner. Every sample is validated: the expected number of calls was made, and every
advice ran in the `aspects` scenario only.

### Scenarios

| Scenario | Application |
|---|---|
| `baseline` | without the bridge: no entry line, no bridge config |
| `empty` | the bridge installed and started from the entry line with its defaults, no aspect configured |
| `aspects` | the bridge with the aspects advising every service |

The **overhead** of a scenario is its median total minus the median total of the baseline.

### Modes

Every mode runs in production (`debug` off for the bridge and the runner) and in debug mode (`--debug=0,1`).

| Mode | Execution | Caches |
|---|---|---|
| `cli-cold` | one PHP process per sample, `opcache.enable_cli=0` | AOP cache emptied before every sample: the first request after a deployment without `aop:warmup` |
| `cli-warm` | one PHP process per sample, `opcache.enable_cli=0` | AOP cache filled: console commands |
| `server-hot` | the standalone server (`php -S`, one worker) with opcache, `--warmup` requests discarded | AOP cache filled by a CLI run before the server starts (as `aop:warmup` at deployment), opcache hot |

The server runs production-like opcache settings: `opcache.validate_timestamps=0` in production mode (`1` with
`revalidate_freq=0` in debug mode), `opcache.file_update_protection=0` (woven files are included right after they are
written), `opcache.jit=disable` (the PHP default, `--jit=tracing` to compare). Every mode sets
`zend.assertions=-1`. The scenarios run interleaved, so a drift of the machine affects them alike.

## Budgets and guards

[`budgets.php`](budgets.php) lists the timing budgets (medians, with about twice the recorded values as headroom).
Besides those, structural rules are always checked:

- a warm production request never loads the weaver, PHP-Parser or the pointcut parser;
- a production boot loads no aspect class: aspects are loaded when the first of their advices runs;
- the bridge without aspects never loads an aspect class.

`--assert` turns violations into a failing exit code; CI runs the benchmark that way. The machine-independent part
is also covered by PHPUnit: `tests/Performance/BootFootprintTest.php` checks the structural rules and that the bridge
without aspects adds at most 30 files to a warm production request.

## Reading the numbers

- Absolute timings depend on the machine; compare the scenarios of one run, not runs of different machines.
- `server-hot` in production mode is what every web request of a deployed application pays. CLI modes run without
  opcache, so their numbers are dominated by compiling PHP files; they matter for console commands.
- `cli-cold` is a one-off cost: run `./yii aop:warmup` at deployment to pay it before the first request.
- The synthetic application is small (its baseline boots in about 1.5 ms with opcache): relative overheads are much
  smaller in a real application, whose container, middleware and controllers cost more.
