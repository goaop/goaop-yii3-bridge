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
- `config/kernel.php` — the `goaop-kernel` group: returns the bridge and alias parameters. Its file is evaluated with
  the params group of the config it is read from, so the entry line sees the parameters of the runner.
- `src/AopBootstrap.php` — the entry line (`AopBootstrap::init($runner->getConfig(), $rootPath)` in every entry script,
  between the creation of the runner and its run): reads the `goaop-kernel` group of the runner's config, boots the
  kernel before the runner builds the container.
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
- Reading the config is evaluating PHP: it autoloads every class a parameter or a tagged definition references (class
  constants, enum cases), before the kernel starts. Such classes can not be woven; in debug mode
  `AopBootstrap::init()` diffs `get_declared_classes()` around the read and reports the ones within the woven paths
  (the framework's `Enumerator` path filter), advised or not. Keep that check simple: no weaving probe (it would need
  the aspects before the container is attached). Do not start the kernel before the tags are read: classes loaded in
  between would be woven without the tagged aspects, and that woven code would be cached.
- The entry line reads the config of the runner, never a `Config` of its own: the runner evaluates every group with its
  own params group (`params-web`, `params-console`), and a definition of the common `di` group may read any of them.
- Aspects are registered inside `configureAop()`, before `AopComposerLoader` serves any class: registering them later
  would weave classes loaded in between without advices.
- Never autoload aspect classes at registration (production keeps them unloaded); `AspectList` checks names
  syntactically, the debug-only `assertAspects()` runs from the bootstrap callback.
- Native lazy proxies: an advice runs with `$this` bound to the proxy, the state is the Yii instance's. Classes without
  instance properties cannot be lazy; their factory runs while weaving. An aspect is always the Yii instance: when
  one is needed before the container is attached, the factory throws. Never create it directly (`new`): its container
  definition would be ignored, and the timing of the weaving (i.e. debug mode) would decide how it is created.
- yiisoft/di exposes no tagged ids without instantiating the services (`get('tag@…')` creates them all), so
  `TaggedAspects` reads the tag declarations from the config: the `tags` meta key of the common `di` group and the
  common `di-tags` tag map, in the container's order (tag map first). Only common groups: web and console share the
  woven cache, so they must weave the same aspects. The entry line uses the config of the runner, the bootstrap
  fallback the `ConfigInterface` the runner registers in the container (the same object).
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

## Remote sessions (Claude Code on the web)

Copied from `tests/AGENTS.md` of goaop/framework (§ Remote sessions), where it was diagnosed; it applies here as is.

### Installing the dependencies, phpstan included

The session's GitHub proxy serves `api.github.com` / `codeload.github.com` dist zips only for the repositories
attached to the session. Every other package answers `403 "GitHub access to this repository is not enabled for this
session"` (no token or composer option changes that), so composer falls back to slow `git clone`s, and
phpstan/phpstan, which has no `source` (it is just the phar), aborts the install with "Could not authenticate against
github.com".

Fix A (recommended, dist for everything): a packagist mirror that hosts its own dist zips; non-GitHub hosts are not
gated by the proxy. mirrors.cloud.tencent.com is up to date and its zips are byte-identical to the GitHub tags
(mirrors.aliyun.com, mirrors.huaweicloud.com and packagist.jp do not work).

```bash
# global (session-local) config, composer.json stays untouched; composer.lock is gitignored, a stale one would pin
# api.github.com dist URLs
COMPOSER_ALLOW_SUPERUSER=1 composer config -g repos.packagist composer https://mirrors.cloud.tencent.com/composer
rm -f composer.lock
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction
./vendor/bin/phpstan --version
```

Fix B (when the mirror is unreachable): seed composer's dist cache for phpstan/phpstan with a zip of a shallow clone
of the release tag (composer looks up `<cache-files-dir>/phpstan/phpstan/<sha1(dist url)>.zip`), and take every other
package from git:

```bash
[ -f composer.lock ] || COMPOSER_ALLOW_SUPERUSER=1 composer update --no-install --no-interaction
eval "$(php -r '$l = json_decode(file_get_contents("composer.lock"), true);
  foreach (array_merge($l["packages"], $l["packages-dev"]) as $p) {
      if ($p["name"] === "phpstan/phpstan") {
          printf("V=%s REF=%s KEY=%s\n", $p["version"], $p["dist"]["reference"], sha1($p["dist"]["url"]));
      }
  }')"
TMP=$(mktemp -d); git clone -q --depth 1 --branch "$V" https://github.com/phpstan/phpstan.git "$TMP/phpstan"
CACHE="$(composer config --global cache-files-dir 2>/dev/null)/phpstan/phpstan"; mkdir -p "$CACHE"
git -C "$TMP/phpstan" archive --format=zip --prefix="phpstan-phpstan-${REF:0:7}/" -o "$CACHE/$KEY.zip" HEAD
COMPOSER_ALLOW_SUPERUSER=1 composer install --prefer-source --no-interaction
```

`composer test` and `composer analyse` refuse to run as root with plugins disabled: run `vendor/bin/phpunit` and
`vendor/bin/phpstan analyse` directly, or prefix them with `COMPOSER_ALLOW_SUPERUSER=1`.

### PHP 8.5.11 tracing JIT

PHP 8.5.11 has an internal bug in the tracing JIT: once the JIT is warm, the pointcut parser of the framework rejects
valid pointcuts (`Unexpected token \`, `Unexpected ( at line 1`, ...). Not a bridge or framework bug. Session images
may enable opcache and `opcache.jit=tracing` for the CLI; disable the JIT for the test run (the functional tests
forward `PHP_INI_SCAN_DIR` to the `yii` processes they start):

```bash
mkdir -p /tmp/php-nojit && echo 'opcache.jit=disable' > /tmp/php-nojit/zz-nojit.ini
PHP_INI_SCAN_DIR=":/tmp/php-nojit" vendor/bin/phpunit
```

Once `php -v` reports 8.5.12 or higher, check that the tests pass with the tracing JIT on and remove this item. The
benchmarks set their own ini (JIT off unless `--jit` is given).
