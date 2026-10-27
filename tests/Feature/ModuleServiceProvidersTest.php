<?php

use App\Providers\ModuleServiceProvider;
use App\Services\Module\ModuleRegistry;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/*
 * A module found by its pnlcs.json may name a service provider (#315): a
 * third-party gateway gets its own checkout pages, webhook URLs and tables
 * without editing routes/web.php or the webhook controller, which an update
 * would put back.
 *
 * Each test writes a real module folder, rebuilds the registry the way the
 * application boots it, boots the module providers and removes the folder.
 */

const ZZ_PROVIDER_DIRS = ['modules/Gateways/ZzOwnPay', 'modules/Gateways/ZzBrokenPay', 'modules/Gateways/ZzPlainPay', 'modules/Gateways/ZzStripeAgain', 'modules/Gateways/ZzOtherPay'];

function writeProviderModule(string $folder, array $manifest, ?string $boot = null): void
{
    $dir = base_path('modules/Gateways/'.$folder);
    $ns = 'Modules\\Gateways\\'.$folder;
    File::ensureDirectoryExists($dir.'/migrations');
    File::put("{$dir}/{$folder}Module.php", "<?php\n\nnamespace {$ns};\n\nclass {$folder}Module extends \\Modules\\Gateways\\BankTransfer\\BankTransferModule {}\n");
    if ($boot !== null) {
        File::put("{$dir}/{$folder}ServiceProvider.php", <<<PHP
<?php

namespace {$ns};

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class {$folder}ServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        {$boot}
    }
}
PHP);
        $manifest += ['provider' => "{$ns}\\{$folder}ServiceProvider"];
    }
    File::put("{$dir}/pnlcs.json", json_encode($manifest + ['type' => 'gateway', 'class' => "{$ns}\\{$folder}Module"]));
}

function bootModuleProviders(): ModuleRegistry
{
    app()->forgetInstance(ModuleRegistry::class);
    $provider = new ModuleServiceProvider(app());
    $provider->register();
    $provider->boot();
    app('router')->getRoutes()->refreshNameLookups();

    return app(ModuleRegistry::class);
}

beforeEach(function () {
    foreach (ZZ_PROVIDER_DIRS as $dir) {
        File::deleteDirectory(base_path($dir));
    }
});

afterEach(function () {
    foreach (ZZ_PROVIDER_DIRS as $dir) {
        File::deleteDirectory(base_path($dir));
    }
    app()->forgetInstance(ModuleRegistry::class);
    (new ModuleServiceProvider(app()))->register();
});

test('a module brings its own routes and migrations through the provider its manifest names', function () {
    writeProviderModule('ZzOwnPay', ['name' => 'zzownpay'],
        "Route::middleware('web')->get('zzownpay/return', fn () => 'zz own return page')->name('zzownpay.return');\n"
        ."        Route::post('zzownpay/notify', fn (\\Illuminate\\Http\\Request \$r) => 'got '.\$r->getContent())->name('zzownpay.notify');\n"
        ."        \$this->loadMigrationsFrom(__DIR__.'/migrations');");

    $registry = bootModuleProviders();

    expect($registry->discoveredProviders())->toBe(['gateway zzownpay' => 'Modules\\Gateways\\ZzOwnPay\\ZzOwnPayServiceProvider'])
        ->and(app('migrator')->paths())->toContain(base_path('modules/Gateways/ZzOwnPay').'/migrations');

    $this->get('/zzownpay/return')->assertOk()->assertSee('zz own return page');
    // A webhook route of the module's own: the exact body reaches it, no CSRF.
    $this->call('POST', '/zzownpay/notify', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{"a":1}')
        ->assertOk()->assertSee('got {"a":1}', false);
});

test('a module without a provider, and one the core shadows, bring none', function () {
    writeProviderModule('ZzPlainPay', ['name' => 'zzplainpay']);
    writeProviderModule('ZzStripeAgain', ['name' => 'stripe'], "Route::get('zz-stripe-again', fn () => 'no');");

    $registry = bootModuleProviders();

    expect($registry->discoveredProviders())->toBe([])
        ->and(app('router')->has('zz-stripe-again'))->toBeFalse();
    $this->get('/zz-stripe-again')->assertNotFound();
});

test('a provider that throws is logged with the module and skipped, and the others still load', function () {
    writeProviderModule('ZzBrokenPay', ['name' => 'zzbrokenpay'], "throw new \\RuntimeException('zz broken boot');");
    writeProviderModule('ZzOtherPay', ['name' => 'zzotherpay'], "Route::middleware('web')->get('zzotherpay/return', fn () => 'still here');");
    Log::spy();

    bootModuleProviders();

    Log::shouldHaveReceived('error')->withArgs(fn ($m) => str_contains($m, 'Module gateway zzbrokenpay: service provider failed to load') && str_contains($m, 'zz broken boot'))->once();
    $this->get('/zzotherpay/return')->assertOk()->assertSee('still here');
});

test('a manifest naming a class that is not a provider is skipped with a warning', function () {
    writeProviderModule('ZzPlainPay', ['name' => 'zzplainpay', 'provider' => 'Modules\\Gateways\\ZzPlainPay\\ZzPlainPayModule']);
    Log::spy();

    bootModuleProviders();

    Log::shouldHaveReceived('warning')->withArgs(fn ($m) => str_contains($m, 'Module gateway zzplainpay') && str_contains($m, 'is not a service provider'))->once();
});
