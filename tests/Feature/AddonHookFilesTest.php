<?php

use App\Models\Setting;
use App\Services\AddonManager;
use Illuminate\Container\Container;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
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

function writeZzHookedAddon(): string
{
    $dir = base_path(ZZ_HOOKED_DIR);
    File::ensureDirectoryExists($dir);
    File::put($dir.'/ZzHookedModule.php', <<<'PHP'
<?php

namespace Modules\Addons\ZzHooked;

use App\Contracts\AddonModuleInterface;
use Illuminate\Http\Request;

class ZzHookedModule implements AddonModuleInterface
{
    public function getName(): string { return 'zz_hooked'; }
    public function getDisplayName(): string { return 'Zz Hooked'; }
    public function getDescription(): string { return 'Test fixture.'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getAuthor(): string { return 'Tests'; }
    public function activate(): array { return ['success' => true]; }
    public function deactivate(): array { return ['success' => true]; }
    public function output(Request $request): string { return ''; }
    public function sidebar(): array { return []; }
    public function config(): array { return []; }
    public function upgrade(string $fromVersion): array { return ['success' => true]; }
}
PHP);
    File::put($dir.'/hooks.php', "<?php\n\n\$GLOBALS['zz_hooked_loaded'] = true;\n");

    return $dir.'/hooks.php';
}

function freshHookedManager(): AddonManager
{
    app()->forgetInstance(AddonManager::class);

    return app(AddonManager::class);
}

beforeEach(fn () => File::deleteDirectory(base_path(ZZ_HOOKED_DIR)));

afterEach(function () {
    File::deleteDirectory(base_path(ZZ_HOOKED_DIR));
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
