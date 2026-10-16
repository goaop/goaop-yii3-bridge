# Contributing

Thanks for helping improve the Go! AOP Yii3 bridge!

## Workflow

1. Fork/branch off `master`.
2. Name your branch using **conventional branch names** (see below).
3. Make your changes with **conventional commits**, one logical change per commit.
4. Ensure the suite is green locally: `composer validate --strict && composer test && composer analyse`.
5. Open a pull request. The PR title must be a valid conventional commit subject — CI checks it.

## Conventional commits

Every commit message follows [Conventional Commits 1.0.0](https://www.conventionalcommits.org/en/v1.0.0/):

```
<type>[optional scope]: <description>

[optional body]

[optional footer(s)]
```

Allowed types:

| Type | Use for |
|---|---|
| `feat` | new user-facing functionality |
| `fix` | bug fixes |
| `chore` | maintenance that touches neither src behavior nor CI |
| `ci` | CI workflows and build tooling |
| `docs` | documentation only |
| `test` | adding or fixing tests only |
| `refactor` | behavior-preserving code changes |
| `build` | composer/dependency/packaging changes |

Breaking changes: append `!` after the type/scope (e.g. `feat!: drop yiisoft/di 1.x support`) and add a
`BREAKING CHANGE:` footer explaining the migration.

Examples:

```
feat(config): resolve aliases in the excludePaths parameter
fix(kernel): attach the container before the debug-mode aspect check
ci: add PHP 8.6 to the experimental matrix
```

## Conventional branches

Branch names mirror commit types, kebab-case after the slash:

```
feat/aspect-priorities
fix/cache-file-mode-string
ci/php86-experimental
docs/readme-deployment
```

## Pull requests

- Keep PRs focused on one concern; stack PRs rather than mixing streams.
- Fill in the PR template; link the issue the PR addresses (`Closes #N`).
- Squash-merge is preferred; the squash commit message must itself be a valid conventional commit (the PR title is
  used, hence the title check).

## Testing notes

- `tests/Support/App` is a small Yii3 application (config directory, hand-written `.merge-plan.php`, `yii` entry
  script). `TestApplication` starts it the way its entry scripts do: `AopBootstrap::init()`, then the console runner.
- Anything that initializes the aspect kernel must run with `#[RunTestsInSeparateProcesses]`: the kernel is a
  process-global singleton, it wraps the composer autoloader and only honours the options of its first initialization.
- Fixture classes of the application must not be autoloaded before the kernel is initialized: reference them with
  `::class` only (which does not autoload) until the test started the application.
- `tests/Functional` runs `tests/Support/App/yii` in its own PHP process, through the real console runner.
- `tests/Performance` checks what a request of the benchmark application loads (deterministic); timings are measured
  by `composer benchmark` (see `benchmarks/README.md`). Run `composer benchmark -- --assert` for changes touching the
  boot path, and update `benchmarks/RESULTS.md` and `benchmarks/budgets.php` when the numbers move.
- Run the suite on every supported PHP version you have, e.g. `php8.4 vendor/bin/phpunit` — with a `vendor/`
  installed by that PHP version.
