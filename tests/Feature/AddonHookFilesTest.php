<?php

use App\Models\Setting;
use App\Services\AddonManager;
use Illuminate\Container\Container;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\File;

/*
 * An addon's hooks.php loads only while the addon is active. activate() stores
 * that under the addon's own name (getName()), but the hook loader asked by
 * folder name, so an addon whose folder differs from its name by more than
 * letter case - ProjectManagement / "project_management" - never had its hooks
 * loaded (on a case-sensitive database StaffBoard / "staffboard" too). The
 * fixture's folder (ZzHooked) differs from its name ("zz_hooked") on purpose.
 */

const ZZ_HOOKED_DIR = 'modules/Addons/ZzHooked';
const ZZ_BADMOD_DIR = 'modules/Addons/ZzBadMod';
const ZZ_HOOKED2_DIR = 'modules/Addons/ZzHookedToo';

function writeZzHookedAddon(string $folder = 'ZzHooked', string $name = 'zz_hooked'): string
{
    $dir = base_path('modules/Addons/'.$folder);
    File::ensureDirectoryExists($dir);
    File::put($dir."/{$folder}Module.php", <<<PHP
<?php

namespace Modules\Addons\\{$folder};

use App\Contracts\AddonModuleInterface;
use Illuminate\Http\Request;

class {$folder}Module implements AddonModuleInterface
{
    public function getName(): string { return '{$name}'; }
    public function getDisplayName(): string { return 'Zz Hooked'; }
    public function getDescription(): string { return 'Test fixture.'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getAuthor(): string { return 'Tests'; }
    public function activate(): array { return ['success' => true]; }
    public function deactivate(): array { return ['success' => true]; }
    public function output(Request \$request): string { return ''; }
    public function sidebar(): array { return []; }
    public function config(): array { return []; }
    public function upgrade(string \$fromVersion): array { return ['success' => true]; }
}
PHP);
    File::put($dir.'/hooks.php', "<?php\n\n\$GLOBALS['{$name}_loaded'] = true;\n");

    return $dir.'/hooks.php';
}

function freshHookedManager(): AddonManager
{
    app()->forgetInstance(AddonManager::class);

    return app(AddonManager::class);
}

beforeEach(function () {
    File::deleteDirectory(base_path(ZZ_HOOKED_DIR));
    File::deleteDirectory(base_path(ZZ_BADMOD_DIR));
    File::deleteDirectory(base_path(ZZ_HOOKED2_DIR));
});

afterEach(function () {
    File::deleteDirectory(base_path(ZZ_HOOKED_DIR));
    File::deleteDirectory(base_path(ZZ_BADMOD_DIR));
    File::deleteDirectory(base_path(ZZ_HOOKED2_DIR));
    unset($GLOBALS['zz_hooked_too_loaded']);
    File::delete(storage_path('logs/zz-hooked-boot.log'));
    unset($GLOBALS['zz_hooked_loaded']);
    freshHookedManager();
});

test('an active addon\'s hooks file is found by the addon\'s own name', function () {
    $file = writeZzHookedAddon();
    Setting::set('addon_zz_hooked_active', '1', 'addons');

    expect(freshHookedManager()->activeHookFiles())->toBe(['zz_hooked' => $file]);
});

test('the folder name does not make an addon active', function () {
    writeZzHookedAddon();
    Setting::set('addon_zz_hooked_active', '0', 'addons');
    Setting::set('addon_ZzHooked_active', '1', 'addons');

    expect(freshHookedManager()->activeHookFiles())->not->toHaveKey('zz_hooked');
});

test('a real boot loads the hooks of an addon whose folder differs from its name', function () {
    writeZzHookedAddon();
    $testApp = Container::getInstance();

    // A fresh application booted the way a request boots it. It has its own
    // database connection and cannot see this test's transaction, so which
    // addons are active is given to it directly, once AppServiceProvider has
    // registered the real manager.
    $app = require base_path('bootstrap/app.php');
    $app->booting(fn ($app) => $app->instance(AddonManager::class, new class extends AddonManager
    {
        public function isActive(string $name): bool
        {
            return $name === 'zz_hooked';
        }
    }));

    try {
        $app->make(HttpKernel::class)->bootstrap();
    } finally {
        Container::setInstance($testApp);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($testApp);
    }

    expect($GLOBALS['zz_hooked_loaded'] ?? false)->toBeTrue();
});

test('an addon whose module file does not parse is skipped, the other addons keep their hooks and provider', function () {
    // its own addon: a hooks.php is required once per process, and the tests above loaded ZzHooked's
    writeZzHookedAddon('ZzHookedToo', 'zz_hooked_too');
    File::put(base_path(ZZ_HOOKED2_DIR).'/ZzHookedTooServiceProvider.php', <<<'PHP'
<?php

namespace Modules\Addons\ZzHookedToo;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ZzHookedTooServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('web')->get('zz-hooked', fn () => 'zz hooked page');
    }
}
PHP);
    // Not even active: a module file that does not parse must still not stop discovery.
    File::ensureDirectoryExists(base_path(ZZ_BADMOD_DIR));
    File::put(base_path(ZZ_BADMOD_DIR).'/ZzBadModModule.php', "<?php\n\nnamespace Modules\\Addons\\ZzBadMod;\n\nclass ZzBadModModule implements {\n");

    $log = storage_path('logs/zz-hooked-boot.log');
    $testApp = Container::getInstance();
    $app = require base_path('bootstrap/app.php');
    $app->booting(fn ($app) => $app->instance(AddonManager::class, new class extends AddonManager
    {
        public function isActive(string $name): bool
        {
            return $name === 'zz_hooked_too';
        }
    }));
    $app->afterBootstrapping(LoadConfiguration::class, function ($app) use ($log) {
        $app['config']->set('logging.channels.zz', ['driver' => 'single', 'path' => $log]);
        $app['config']->set('logging.default', 'zz');
    });

    try {
        $status = $app->make(HttpKernel::class)->handle(Request::create('/zz-hooked'))->getStatusCode();
    } finally {
        Container::setInstance($testApp);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($testApp);
    }

    expect($status)->toBe(200)
        ->and($GLOBALS['zz_hooked_too_loaded'] ?? false)->toBeTrue()
        ->and(File::get($log))->toContain('Addon ZzBadMod: module file failed to load, skipped');
});
