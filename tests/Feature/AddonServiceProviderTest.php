<?php

use App\Models\Client;
use App\Models\Setting;
use App\Models\User;
use App\Providers\AddonServiceProvider;
use App\Services\AddonManager;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/*
 * Addons could add hooks and an admin page, but no page of their own for
 * customers or visitors: the bundled Ksef module gets its routes from a
 * provider listed by hand in bootstrap/providers.php, which an operator's
 * addon cannot do. An active addon may now ship
 * modules/Addons/<Name>/<Name>ServiceProvider.php and use the framework's own
 * means (routes, views, migrations, schedule) through it.
 *
 * Each test writes a real addon folder, rebuilds the manager the way the
 * application boots it and removes the folder afterwards. The fixture's
 * folder (ZzAddonPage) differs from its getName() ("zzaddonpage") on purpose,
 * as StaffBoard's does, so the active check is proven to use the name that
 * activate() stores.
 */

const ZZ_ADDON_DIRS = ['modules/Addons/ZzAddonPage', 'modules/Addons/ZzAddonBroken'];

function zzAddonModule(string $folder, string $name): string
{
    return <<<PHP
<?php

namespace Modules\Addons\\{$folder};

use App\Contracts\AddonModuleInterface;
use Illuminate\Http\Request;

class {$folder}Module implements AddonModuleInterface
{
    public function getName(): string { return '{$name}'; }
    public function getDisplayName(): string { return 'Zz Addon Page'; }
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
PHP;
}

function writeZzAddon(string $providerBoot, string $folder = 'ZzAddonPage', string $name = 'zzaddonpage'): void
{
    $dir = base_path('modules/Addons/'.$folder);
    File::ensureDirectoryExists($dir.'/views');
    File::put($dir."/{$folder}Module.php", zzAddonModule($folder, $name));
    File::put($dir."/{$folder}ServiceProvider.php", <<<PHP
<?php

namespace Modules\Addons\\{$folder};

use Illuminate\Support\ServiceProvider;

class {$folder}ServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        {$providerBoot}
    }
}
PHP);
    File::put($dir.'/routes.php', <<<'PHP'
<?php

use Illuminate\Support\Facades\Route;

// A public page and a client-area page behind the client area's own guards.
Route::middleware('web')->get('zz-addon-public', fn () => 'zz public page')->name('zzaddonpage.public');
Route::middleware(['web', 'banned.ip', 'client.permission', 'auth', '2fa'])
    ->prefix('client')->name('client.')
    ->group(function () {
        Route::get('zz-addon-page', fn () => view('zzaddonpage::page'))->name('zzaddonpage');
    });
PHP);
    File::put($dir.'/views/page.blade.php', 'zz client page for {{ auth()->user()->first_name }}');
}

function freshAddonManager(): AddonManager
{
    app()->forgetInstance(AddonManager::class);

    return app(AddonManager::class);
}

function bootAddonProviders(): void
{
    (new AddonServiceProvider(app()))->boot();
    app('router')->getRoutes()->refreshNameLookups();
}

beforeEach(function () {
    foreach (ZZ_ADDON_DIRS as $dir) {
        File::deleteDirectory(base_path($dir));
    }
});

afterEach(function () {
    app()->forgetInstance('routes.cached');
    foreach (ZZ_ADDON_DIRS as $dir) {
        File::deleteDirectory(base_path($dir));
    }
    File::delete(app()->getCachedRoutesPath());
    freshAddonManager();
});

test('an active addon brings its own pages through its service provider', function () {
    writeZzAddon("\$this->loadRoutesFrom(__DIR__.'/routes.php');\n        \$this->loadViewsFrom(__DIR__.'/views', 'zzaddonpage');");
    Setting::set('addon_zzaddonpage_active', '1', 'addons');

    expect(freshAddonManager()->activeProviders())->toContain('Modules\\Addons\\ZzAddonPage\\ZzAddonPageServiceProvider');
    bootAddonProviders();

    // A visitor sees the public page and is sent to sign in for the client page.
    $this->get('/zz-addon-public')->assertOk()->assertSee('zz public page');
    $this->get('/client/zz-addon-page')->assertRedirect();

    $user = User::factory()->create(['first_name' => 'Zeynep', 'email' => 'zz_'.uniqid().'@example.com']);
    $client = Client::factory()->create(['email' => $user->email]);
    $user->clients()->attach($client->id, ['owner' => true, 'permissions' => null]);

    $this->actingAs($user)->get(route('client.zzaddonpage'))->assertOk()->assertSee('zz client page for Zeynep');
});

test('an inactive addon\'s provider is not loaded', function () {
    writeZzAddon("\$this->loadRoutesFrom(__DIR__.'/routes.php');");
    Setting::set('addon_zzaddonpage_active', '0', 'addons');

    expect(freshAddonManager()->activeProviders())->not->toContain('Modules\\Addons\\ZzAddonPage\\ZzAddonPageServiceProvider');
});

test('addons without a provider file add none', function () {
    // The bundled addons ship no provider: activating them changes nothing here.
    Setting::set('addon_staffboard_active', '1', 'addons');
    Setting::set('addon_projectmanagement_active', '1', 'addons');

    expect(freshAddonManager()->activeProviders())->toBe([]);
});

test('a provider that throws is logged and skipped, not fatal', function () {
    // Its own folder and class: a class name can only be loaded once per process.
    writeZzAddon("throw new \\RuntimeException('broken addon');", 'ZzAddonBroken', 'zzaddonbroken');
    Setting::set('addon_zzaddonbroken_active', '1', 'addons');
    freshAddonManager();

    Log::shouldReceive('error')->once()->withArgs(fn ($message) => str_contains($message, 'ZzAddonBrokenServiceProvider') && str_contains($message, 'broken addon'));

    bootAddonProviders();
});

test('activating or deactivating an addon clears cached routes', function () {
    writeZzAddon('//');
    $manager = freshAddonManager();
    $cache = app()->getCachedRoutesPath();

    // An application booted after `php artisan optimize`: Laravel decides once, at boot, that routes are cached.
    app()->instance('routes.cached', true);
    File::put($cache, '<?php // cached by php artisan optimize');
    $manager->activate('zzaddonpage');
    expect(File::exists($cache))->toBeFalse();

    File::put($cache, '<?php // cached again');
    $manager->deactivate('zzaddonpage');
    expect(File::exists($cache))->toBeFalse();
});
