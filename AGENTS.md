# Agent guidance for goaop/goaop-yii3-bridge

Canonical guidance for AI coding agents (and a quick orientation for humans) working on this repository.

## What this package is

An integration bridge that plugs the [Go! AOP framework](https://github.com/goaop/framework) into Yii3 applications.
It starts the aspect kernel before the Yii runner autoloads application classes, registers aspects as lazy services
resolved from the Yii container, and adds the `aop:*` console commands.

- Target runtime: PHP >= 8.4, Yii3 (yiisoft/config, yiisoft/di, yiisoft/aliases), goaop/framework 4.x
  (attribute-based aspects only).
- Package type: `library` with yiisoft/config plugin groups (`extra.config-plugin` in `composer.json`).
- Namespace: `Go\Yii3\GoAopBridge\` (`src/`), tests `Go\Yii3\GoAopBridge\Tests\` (`tests/`), fixture application
  `Go\Yii3\GoAopBridge\Tests\App\` (`tests/Support/App/src/`).

## Architecture map

- `config/params.php` — bridge parameters under `goaop/goaop-yii3-bridge` (aliases allowed).
- `config/di.php` — `KernelOptions`, `AspectList`, `AspectYii3Kernel`/`AspectKernel`, `AspectContainer` definitions.
- `config/bootstrap.php` — `KernelInitializer::initialize()`: attaches the Yii container to the kernel, initializes the
  kernel itself when the entry line was not used (fallback), checks the aspects in debug mode.
- `config/params-console.php` — registers the commands with yii-console (autowired, no `di-console` needed).
- `src/AopBootstrap.php` — the entry line (`AopBootstrap::init()` in the app's `src/bootstrap.php`): builds a
  yiisoft/config `Config`, reads the `params` group, boots the kernel before the runner builds the container.
- `src/Kernel/AspectYii3Kernel.php` — `configureAop()` registers every aspect with `addLazyService()`; the factory
  resolves it through `ContainerResolver` (the Yii container, attached later by the bootstrap callback).
- `src/Config/KernelOptions.php`, `src/Config/AspectList.php` — typed, validated parameters; `AspectList` keeps the
  origin (parameter or tag) of every aspect for error messages.
- `src/Config/TaggedAspects.php` — aspects tagged `goaop.aspect` (the `aspectTag` param) in the container config.
- `src/Command/*` — subclasses of the framework commands; `InjectedKernel` drops the `loader` argument.
- `benchmarks/` — boot-time benchmarks (namespace `Go\Yii3\GoAopBridge\Benchmarks\`): a generated application, a
  timed front controller (`public/index.php`), CLI and `php -S` runs, budgets; results in `benchmarks/RESULTS.md`.

## Load-bearing constraints (do not "simplify" these away)

- Kernel start must happen in the entry script, not in a `bootstrap` callback: in debug mode yiisoft/definitions
  (>= 3.4) reflects every array definition while the container is built, and the HTTP runner creates the error handler
  before the bootstrap group runs. The bootstrap fallback exists, but misses those classes.
- Aspects are registered inside `configureAop()`, before `AopComposerLoader` serves any class: registering them later
  would weave classes loaded in between without advices.
- Never autoload aspect classes at registration (production keeps them unloaded); `AspectList` checks names
  syntactically, the debug-only `assertAspects()` runs from the bootstrap callback.
- Native lazy proxies: an advice runs with `$this` bound to the proxy, the state is the Yii instance's. Classes without
  instance properties cannot be lazy; their factory runs while weaving — before the container is attached, they are
  instantiated directly (`AspectYii3Kernel::instantiateStatelessAspect()`).
- yiisoft/di exposes no tagged ids without instantiating the services (`get('tag@…')` creates them all), so
  `TaggedAspects` reads the tag declarations from the config: the `tags` meta key of the common `di` group and the
  common `di-tags` tag map, in the container's order (tag map first). Only common groups: web and console share the
  woven cache, so they must weave the same aspects. The entry line uses the `Config` it builds, the bootstrap
  fallback the `ConfigInterface` the runner registers in the container.
- `Go\Core\AspectKernel` is a process-global singleton with a `final protected` constructor. Tests touching the kernel
  run in separate PHPUnit processes, each with its own `@runtime` (`TestApplication::prepareRuntime()`).
- `cacheFileMode` must reach the kernel as an `int`; strings of octal digits are converted by `KernelOptions`.
- Aspects use PHP 8 attributes (`Go\Lang\Attribute\Before`, `After`, `Around`, `AfterThrowing`, `Pointcut`); advice
  methods must be `public`. No Doctrine annotations.

## Development commands

```bash
composer install            # needs minimum-stability: dev deps (goaop/framework 4.x-dev)
composer validate --strict
composer test               # phpunit
composer analyse            # phpstan, level max (src, config, tests, benchmarks)
composer benchmark          # boot-time benchmarks, see benchmarks/README.md (-- --quick --assert for a smoke run)
```

## Conventions

- **Conventional Commits 1.0.0** for every commit message: `type(optional-scope): description`. Allowed types:
  `feat`, `fix`, `chore`, `ci`, `docs`, `test`, `refactor`, `build`. Breaking changes: `!` after the type/scope and a
  `BREAKING CHANGE:` footer. One logical change per commit.
- **Conventional branch names**: `feat/…`, `fix/…`, `chore/…`, `ci/…`, `docs/…`, `test/…`, `refactor/…`.
- PR titles must be valid conventional commit subjects — CI enforces this (`.github/workflows/lint-pr.yml`).
- New classes are `final` unless meant to be extended. See `CONTRIBUTING.md` for the full workflow.

## Things agents get wrong here

- Performance claims need numbers from `composer benchmark`: keep `benchmarks/RESULTS.md` and `benchmarks/budgets.php`
  in sync with them. The benchmark sets its own ini settings (CLI without opcache, server with opcache, JIT off by
  default); do not compare CLI and server numbers.

- The fixture merge plan (`tests/Support/App/config/.merge-plan.php`) is hand-written in the format of the yiisoft/config
  plugin; the bridge itself is listed under the vendor override package `//`. Mark vendor files that differ between
  dependency versions optional (`?config/...`) so the `--prefer-lowest` job keeps working.
- `vendor/` is tied to the PHP version that installed it: test PHP 8.4 and 8.6 with their own install
  (`--ignore-platform-req=php+` for 8.6).
- Do not add a `containerClass` option with `Go\Core\GoAspectContainer` (removed upstream) and do not call
  `registerAspect()` (removed in framework 4.x: aspects are `add()`/`addLazyService()` services).
- The upstream 4.x line is unreleased: composer requires `minimum-stability: dev` + `prefer-stable: true`. Do not
  "fix" that to stable until goaop/framework 4.0 is tagged.
