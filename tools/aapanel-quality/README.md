# aaPanel local quality gate

This is free, local tooling. It does not use GitHub Actions, paid services, provider credentials, live provider endpoints or deployments. PHP 8.4+, Composer and Node.js (for the JavaScript setup regression) are required. Run commands from the repository root.

## Install pinned tools

```sh
composer install --no-plugins --no-scripts
composer install --working-dir=tools/aapanel-quality --no-plugins --no-scripts
```

The first command uses the application's existing lock file; the second installs PHP_CodeSniffer **4.0.4**, pinned in this folder's separate lock file. Do not run Composer update to reproduce this gate. Tool vendor files, runtime binaries, caches and local database files are not part of the source deliverable.

## Complexity gate

```sh
./tools/aapanel-quality/run.sh
```

The script runs the stock PHPCS gate first, then stock plus supplemental anonymous-function checks. Either failure stops the script. The maximum is **10** throughout, including test methods, closures and arrow functions.

The XML configurations explicitly cover:
- `modules/Servers/AaPanel/`
- `app/Contracts/RequiresProvisioningLock.php`
- `app/Services/ProvisioningService.php`
- `app/Services/Module/ModuleRegistry.php`
- `tests/Feature/AaPanelModuleTest.php`
- `tests/Feature/AaPanelSetupTest.php`
- `tests/Feature/AaPanelProvisioningLockTest.php`
- This tool's maintained PHP sniff source

Use `PHP_BINARY=/path/to/php` to select a runtime, or put your preferred PHP/`phpenv` shim on `PATH`. An existing pinned installation can be selected with `PHPCS_BIN=/path/to/vendor/bin/phpcs`.

### Definition and caveats

Authoritative lint is PHPCS 4.0.4 `Generic.Metrics.CyclomaticComplexity`, with warning and absolute-error thresholds both 10. Its metric starts at 1 and increments for `case`, `default`, `catch`, `if`, `for`, `foreach`, `while`, `elseif`, ternary `?`, `??`, `??=`, match arms and nullsafe object operators. It does not add increments for Boolean `&&`/`||`.

The stock metric registers named functions/methods. Nested callable predicate tokens remain included in the enclosing method total, so moving a large body into an inline closure does not hide it. The small supplemental sniff registers closures and arrow functions and inherits the upstream `process()` implementation unchanged. It is an additional check, not a substitute counting algorithm. Overlapping scopes are not additive.

### Optional complete measurements

```sh
./tools/aapanel-quality/run.sh metrics
./tools/aapanel-quality/run.sh metrics /path/to/report.json
```

Reporting-only configuration sets the threshold to zero to emit every measured callable's value. These messages are intentionally recorded as PHPCS errors; the wrapper handles their expected exit status. This reporting mode does not certify a passing lint gate. Run the normal gate separately. The default output is ignored `tools/aapanel-quality/reports/complexity.json`; reports contain local source paths.

## Isolated regression tests

```sh
php vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php \
  tests/Feature/AaPanelModuleTest.php \
  tests/Feature/AaPanelSetupTest.php \
  tests/Feature/AaPanelProvisioningLockTest.php
```

These explicit PHPUnit classes use fake HTTP and isolated in-memory SQLite instead of the repository's global Pest/MySQL fixtures. Require PHP XML/DOM, mbstring and pdo_sqlite extensions. Do not substitute the broad live-server suite. They verify contracts and regressions without proving compatibility with every real aaPanel release, entitlement or server configuration.

## Static analysis and formatting

```sh
php vendor/bin/phpstan analyse --configuration=phpstan.neon --no-progress \
  modules/Servers/AaPanel app/Contracts/RequiresProvisioningLock.php \
  app/Services/ProvisioningService.php app/Services/Module/ModuleRegistry.php
php vendor/bin/pint --test \
  modules/Servers/AaPanel app/Contracts/RequiresProvisioningLock.php \
  app/Services/ProvisioningService.php app/Services/Module/ModuleRegistry.php \
  tests/Feature/AaPanelModuleTest.php tests/Feature/AaPanelSetupTest.php \
  tests/Feature/AaPanelProvisioningLockTest.php tools/aapanel-quality/AapanelQuality
```

PHPStan uses the repository's level-6 Larastan configuration. These commands check only their explicit scope and do not claim the entire application passes static analysis.

Adjacent Pest suites inherit a MySQL reconnect from `Tests/TestCase.php`; run them only with a dedicated disposable database and explicit test configuration. Do not point tests or migrations at an existing installation. A runtime that cannot bind a private test-database socket leaves those database-backed regressions unverified; it is not a reason to change system security settings.
