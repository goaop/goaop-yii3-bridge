# Go! AOP Yii3 bridge

[![CI](https://github.com/goaop/goaop-yii3-bridge/actions/workflows/ci.yml/badge.svg)](https://github.com/goaop/goaop-yii3-bridge/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/goaop/goaop-yii3-bridge/graph/badge.svg)](https://codecov.io/gh/goaop/goaop-yii3-bridge)
![PHPStan Badge](https://img.shields.io/badge/PHPStan-level%20max-brightgreen.svg?style=flat)
[![GitHub release](https://img.shields.io/github/release/goaop/goaop-yii3-bridge.svg)](https://github.com/goaop/goaop-yii3-bridge/releases/latest)
[![Total Downloads](https://img.shields.io/packagist/dt/goaop/goaop-yii3-bridge.svg)](https://packagist.org/packages/goaop/goaop-yii3-bridge)
[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%208.4-8892BF.svg)](https://www.php.net/supported-versions.php)
[![License](https://img.shields.io/packagist/l/goaop/goaop-yii3-bridge.svg)](https://packagist.org/packages/goaop/goaop-yii3-bridge)
[![Sponsor](https://img.shields.io/badge/Sponsor-❤️-lightgray?style=flat&logo=github)](https://github.com/sponsors/lisachenko)

Integration bridge for the [Go! AOP framework](https://github.com/goaop/framework) and [Yii3](https://www.yiiframework.com/).
Declare cross-cutting behavior once, as an aspect with PHP 8 attributes, and the engine weaves it into the matching
methods of your services when their classes are loaded. You don't run a code generation step or write proxy wrappers,
and services are created and injected exactly as before.

## Why Aspect-Oriented Programming in Yii3?

Some logic doesn't belong to any single class, yet ends up copied into many of them: logging, caching, metrics,
transactions, authorization checks, retry policies. PSR-15 middleware solves this for HTTP requests. AOP solves it
for **any method of any service**: define the behavior once as an aspect, say *where* it applies with a pointcut
expression, and the engine weaves it in. Your services stay plain business logic.

### Highlights

- 🪡 **Transparent weaving.** Classes are transformed when they are loaded. Your services don't extend a base class,
  implement an interface or get wrapped in a proxy, and the container builds and injects them as usual.
- 🏷️ **PHP 8 attributes.** Advices are declared with `#[Before]`, `#[After]`, `#[Around]` and `#[AfterThrowing]` on
  the methods of your aspect, using pointcut expressions (`execution(public App\Service\**->*(*))`).
- 💤 **Lazy aspects with Yii DI.** Every aspect is a regular service of the Yii container, registered by tagging it
  `goaop.aspect`: constructor injection, autowiring and definitions work as for any other service. The engine resolves an aspect only when one of its advices
  runs for the first time. Until then the aspect class is not even loaded in production.
- 🧩 **Yii3-native configuration.** The bridge plugs into [yiisoft/config](https://github.com/yiisoft/config) groups.
  Settings live under `params['goaop/goaop-yii3-bridge']`, accept aliases such as `@root`, `@runtime` and `@src`,
  differ per environment like any other parameter, and can be extended by other packages. The `aop:*` commands are
  added to `./yii`.
- 🚀 **Production-friendly.** Run `./yii aop:warmup` at deploy time. With `debug` off, woven classes are served from
  the cache through Composer's class map, which works well with opcache.
- 🔬 **Pure PHP.** Built on Go! AOP 4.x: no PECL extension, no `eval()`, and every transformation produces readable
  static PHP files.

## Requirements

- PHP >= 8.4
- Yii3 packages: yiisoft/config, yiisoft/di, yiisoft/aliases (as in the [application template](https://github.com/yiisoft/app))
- goaop/framework 4.x (attribute-based aspects)

## Installation

```bash
composer require goaop/goaop-yii3-bridge
```

The yiisoft/config plugin picks up the configuration of the bridge (run `composer yii-config-rebuild` if you
installed it with the plugin disabled).

> [!NOTE]
> Until goaop/framework 4.0 is tagged, the bridge depends on the unreleased `4.0-dev` line, so the `composer.json`
> of your application needs:
>
> ```json
> "minimum-stability": "dev",
> "prefer-stable": true
> ```

### Start the kernel from the entry script

Add one line to every entry script (`public/index.php`, `yii`, and the bootstrap of your functional tests), between the
creation of the runner and its run. `public/index.php` of the [application template](https://github.com/yiisoft/app)
becomes:

```php
use App\Environment;
use Go\Yii3\GoAopBridge\AopBootstrap;
use Yiisoft\Yii\Runner\Http\HttpApplicationRunner;

// ...

$runner = new HttpApplicationRunner(
    rootPath: dirname(__DIR__),
    debug: Environment::appDebug(),
    checkEvents: Environment::appDebug(),
    environment: Environment::appEnv(),
);

AopBootstrap::init($runner->getConfig(), rootPath: dirname(__DIR__));

$runner->run();
```

and `yii` does the same with its `ConsoleApplicationRunner` (`rootPath: __DIR__`).

`AopBootstrap::init()` reads the bridge parameters, the aliases and the tagged aspects from the config of the runner:
the parameters it sees are the ones of that runner (`params-web` or `params-console`, with `params` nested), the
container definitions are evaluated with them, as the runner does later. The runner keeps that config, so the
parameters are not built twice.

Why the entry script? Weaving only applies to classes loaded after the kernel starts. The Yii runner autoloads
classes while it builds the container, before any `bootstrap` callback runs. In debug mode, for example, every
class with an array definition is reflected for validation, and the HTTP runner creates the error handler early.

If you skip this step, the `bootstrap` config group of the bridge still starts the kernel. Weaving then starts late:
autowired services are woven, but classes loaded while the container was built are not. In debug mode that
includes every class with an array definition, so behavior would differ between development and production.

## Defining an aspect

An aspect is a plain class implementing `Go\Aop\Aspect`, with advice methods declared by attributes. It is a
regular service of the Yii container, so its constructor dependencies are injected:

```php
<?php

declare(strict_types=1);

namespace App\Aspect;

use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Attribute\Around;
use Go\Lang\Attribute\Before;
use Psr\Log\LoggerInterface;

final readonly class LoggingAspect implements Aspect
{
    public function __construct(private LoggerInterface $logger) {}

    #[Before('execution(public App\Service\**->*(*))')]
    public function logCall(MethodInvocation $invocation): void
    {
        $this->logger->debug('Calling {method}', ['method' => $invocation->getMethod()->getName()]);
    }

    #[Around('execution(public App\Service\PaymentService->charge(*))')]
    public function measureCharge(MethodInvocation $invocation): mixed
    {
        $start = hrtime(true);
        try {
            return $invocation->proceed();
        } finally {
            $this->logger->info('charge() took {ms}ms', ['ms' => (hrtime(true) - $start) / 1e6]);
        }
    }
}
```

### Registering aspects

Tag the aspect with `goaop.aspect` in a container definition of the common `di` config group, e.g. in
`config/common/di/aspects.php`. The same definition configures the constructor arguments that can't be autowired:

```php
use App\Aspect\AuditAspect;
use App\Aspect\LoggingAspect;
use Yiisoft\Definitions\Reference;

return [
    LoggingAspect::class => [
        'tags' => ['goaop.aspect'],
    ],
    AuditAspect::class => [
        '__construct()' => [
            'logger' => Reference::to('auditLogger'),
            'level'  => $params['app']['auditLevel'],
        ],
        'tags' => ['goaop.aspect'],
    ],
];
```

A definition with a dedicated `definition` key (`['definition' => static fn() => ..., 'tags' => ['goaop.aspect']]`)
and a tag map in a common `di-tags` config group (`['goaop.aspect' => [LoggingAspect::class]]`) work too.

The Yii container can only return tagged services by creating all of them. So the bridge reads the tag declarations
from the configuration instead and creates no aspect until one of its advices runs. Keep these tags in the common
groups: web and console processes share the woven cache, so they must weave the same aspects. Tags of
`di-web`/`di-console` definitions and of service providers are not read.

Alternatively, list the aspect classes in the `aspects` parameter of `config/common/params.php`:

```php
'goaop/goaop-yii3-bridge' => [
    'aspects' => [
        App\Aspect\LoggingAspect::class,
    ],
],
```

Both ways can be combined, and an aspect found by both is registered once. Tagged services must be registered under
their class name, the way the container validates tags.

To find the tags, the entry line evaluates the common `di` and `di-tags` groups before the kernel starts. Any class
those files (or the parameters) load, e.g. by a class constant or an enum case in a definition, is loaded unwoven. In
debug mode, `AopBootstrap::init()` fails with an error naming such a class when it lies within the woven paths, whether
an aspect advises it or not. Use the value instead of the class there, exclude the class from weaving
(`excludePaths`), or set `aspectTag` to `null` and list your aspects in `aspects`: the definitions are then read by the
runner only, after the kernel started.

See the Go! AOP documentation on [advices](https://github.com/goaop/framework/blob/master/docs/advices.md) and
[pointcuts](https://github.com/goaop/framework/blob/master/docs/pointcuts.md) for the full syntax.

### How aspects are resolved

The bridge registers every tagged or listed class as a *deferred* service of the aspect container. The factory of that service
asks the Yii container for the aspect:

1. The class names are only validated syntactically, so nothing is autoloaded when the kernel starts.
2. When the weaver needs the advices of an aspect (on the first load of a matching class, or by `aop:warmup`), it
   reads them from the class through reflection. The aspect object is a native lazy proxy and is not constructed yet.
3. On the first call of one of its advices, the proxy is initialized with `$container->get(AspectClass::class)`. That
   is the shared instance of the Yii container, with all its dependencies.

So an aspect depending on a database connection, an HTTP client or a heavy service doesn't create it on requests that
never reach an advised method.

An aspect is always the instance of the Yii container. PHP can't make a class without instance properties lazy, so the
weaver creates such an aspect as soon as it reads the advices. That works once the container is attached; when a class
is woven while the container is still being built, the bridge reports an error instead of creating the aspect around
its definition. In debug mode that is the rule rather than the exception: the container loads every class with an
array definition to validate it. Give such an aspect an instance property, e.g. a dependency injected by its
constructor, and it is resolved lazily like any other.

In debug mode the bridge checks that every tagged or listed class exists and implements `Go\Aop\Aspect`, and the error
names the tag or parameter it comes from. Otherwise the aspect container would silently ignore it.

### Aspects contributed by packages

A package tags its aspects in its own `di` config the same way, or contributes them from its own `config/params.php`
(the `params` group is merged recursively and lists are appended):

```php
return [
    'goaop/goaop-yii3-bridge' => [
        'aspects' => [
            Vendor\Package\Aspect\CacheAspect::class,
        ],
    ],
];
```

Duplicates are registered once.

## Configuration

The defaults live in the `params` group of the bridge. Override them in your `config/common/params.php`, or per
environment in `config/environments/<env>/params.php`:

| Parameter       | Default            | Purpose |
|-----------------|--------------------|---------|
| `debug`         | `null`             | Re-weave when sources or aspects change, breakpoints work in the original sources. `null` follows the `GOAOP_DEBUG`, then the `APP_DEBUG` environment variable. Keep it off in production. |
| `appDir`        | `'@root'`          | Application root, only files below it are woven. |
| `cacheDir`      | `'@runtime/aop'`   | Woven sources, proxies and advisor caches. |
| `cacheFileMode` | `null`             | Permissions of cache files: an integer (`0770`) or octal digits (`'770'`, e.g. from an environment variable). `null` means `0770` minus the umask. |
| `features`      | `0`                | Bitmask of `Go\Aop\Features` flags, e.g. `Features::PREBUILT_CACHE`. |
| `includePaths`  | `['@src']`         | Only these directories are woven. Keep it narrow. |
| `excludePaths`  | `[]`               | Never woven. The cache directory and the framework are always excluded. |
| `aspects`       | `[]`               | Aspect classes, resolved lazily from the Yii container. |
| `aspectTag`     | `'goaop.aspect'`   | Container tag registering aspects, read from the common `di` and `di-tags` config groups. `null` disables it. |

A malformed parameter fails fast with a message naming it.

The kernel is available in the container as `Go\Core\AspectKernel` (and `Go\Yii3\GoAopBridge\Kernel\AspectYii3Kernel`),
its aspect container as `Go\Core\AspectContainer`.

## Console commands

With [yiisoft/yii-console](https://github.com/yiisoft/yii-console) installed, the bridge adds:

| Command                 | Purpose |
|-------------------------|---------|
| `./yii aop:warmup`      | Weaves every class below the include paths into the cache. Fails if a file can't be woven; `--fail-fast` stops at the first error. |
| `./yii aop:debug:aspect`| Lists the aspects with their pointcuts and advisors (`--aspect=App\\Aspect\\LoggingAspect` for one). |
| `./yii aop:debug:advisor`| Lists the advisors, or the classes and members one advisor matches (`--advisor=...`). |
| `./yii aop:debug:weaving`| Weaves twice and reports proxies that differ, which reveals circular dependencies between aspects and woven classes. |

They are the framework's own commands, with the kernel injected by the container instead of a `loader` argument.

## Deployment

Weaving happens on the first load of each class. In production, turn `debug` off and build the cache at deploy time:

```bash
./yii aop:warmup
```

On read-only file systems add `Features::PREBUILT_CACHE` to `features`. The engine then trusts the cache completely
and never writes to it, so rebuild it on every deploy.

## Performance

Measured with the [benchmarks](benchmarks/README.md) (`composer benchmark`), on a small synthetic application whose
baseline boots in about 1.5 ms with opcache:

- In production, with opcache and a warm AOP cache, the bridge adds **about 0.5 ms per request without aspects**:
  the entry line reads the params, the tagged aspects and starts the kernel. No aspect class is loaded at boot, and
  the weaver and the parsers are never loaded once the cache is warm.
- Registering aspects costs a few µs per request at most; an aspect is resolved from the container (about 15 µs)
  only when one of its advices runs for the first time.
- Debug mode recompiles woven classes on every request (about 70 µs each), and the first request after a deployment
  weaves every class it loads: keep `debug` off in production and run `./yii aop:warmup` at deployment.

The full results, per mode and phase, are in [benchmarks/RESULTS.md](benchmarks/RESULTS.md).

## Caveats

- Only classes under `appDir` and `includePaths` are woven. Vendor code is not.
- Classes loaded before `AopBootstrap::init()`, such as `App\Environment` and anything the entry script touches
  before that line, can't be woven.
- Classes the configuration loads while the entry line reads it (the parameters, and the `di`/`di-tags` groups for
  tagged aspects) can't be woven either: in debug mode the bridge reports the ones within the woven paths.
- Aspects need no weaving themselves: exclude their directory (e.g. `'excludePaths' => ['@src/Aspect']`). An aspect
  that depends on a class it advises creates a circular dependency, which `aop:debug:weaving` reports.
- The aspect kernel is process-global. Long-running workers (RoadRunner, Swoole, FrankenPHP) initialize it once per
  worker, and every request reuses the woven classes and the aspect instances. Aspects holding per-request state need
  to be reset like any other shared service.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

## License

MIT, see [LICENSE](LICENSE).
