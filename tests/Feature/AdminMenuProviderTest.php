<?php

use App\Models\Admin;
use App\Models\Setting;
use App\Services\AddonManager;
use Illuminate\Support\Facades\File;

function writeAdminMenuAddon(string $folder, string $body): void
{
    $dir = base_path('modules/Addons/'.$folder);
    File::ensureDirectoryExists($dir);
    $name = strtolower($folder);
    File::put($dir.'/'.$folder.'Module.php', <<<PHP
<?php

namespace Modules\Addons\\{$folder};

use App\Contracts\\AddonModuleInterface;
use App\Contracts\\AdminMenuProvider;
use Illuminate\\Http\\Request;

class {$folder}Module implements AddonModuleInterface, AdminMenuProvider
{
    public function getName(): string { return '{$name}'; }
    public function getDisplayName(): string { return '{$folder}'; }
    public function getDescription(): string { return 'Test fixture.'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getAuthor(): string { return 'Tests'; }
    public function activate(): array { return ['success' => true]; }
    public function deactivate(): array { return ['success' => true]; }
    public function output(Request \$request): string { return ''; }
    public function sidebar(): array { return []; }
    public function adminMenu(): array { {$body} }
    public function config(): array { return []; }
    public function upgrade(string \$fromVersion): array { return ['success' => true]; }
}
PHP);
}

function adminMenuManager(): AddonManager
{
    app()->forgetInstance(AddonManager::class);

    return app(AddonManager::class);
}

const ADMIN_MENU_TEST_DIRS = [
    'modules/Addons/ZzAdminMenuOff',
    'modules/Addons/ZzAdminMenuPage',
    'modules/Addons/ZzAdminMenuInvalid',
];

afterEach(function () {
    foreach (ADMIN_MENU_TEST_DIRS as $dir) {
        File::deleteDirectory(base_path($dir));
    }
});

it('collects billing entries from active admin menu providers only', function () {
    writeAdminMenuAddon('ZzAdminMenuOff', "return [['location' => 'billing', 'label' => 'Zz Billing', 'url' => '/admin/zz-billing']];");
    Setting::set('addon_zzadminmenuoff_active', '0', 'addons');

    expect(adminMenuManager()->getAdminMenuItems())->toBe([]);

    Setting::set('addon_zzadminmenuoff_active', '1', 'addons');

    expect(adminMenuManager()->getAdminMenuItems()['billing'])
        ->toContain(['label' => 'Zz Billing', 'url' => '/admin/zz-billing', 'children' => []]);
});

it('shows billing entries in the billing navigation', function () {
    writeAdminMenuAddon('ZzAdminMenuPage', "return [['location' => 'billing', 'label' => 'Zz Billing', 'url' => '/admin/zz-billing']];");
    Setting::set('addon_zzadminmenupage_active', '1', 'addons');
    adminMenuManager();

    $this->actingAs(Admin::factory()->create(), 'admin')
        ->get(route('admin.config.addons.modules'))
        ->assertOk()
        ->assertSee('Zz Billing')
        ->assertSee('href="/admin/zz-billing"', false);
});

it('renders KSeF through the admin menu provider', function () {
    Setting::set('addon_ksef_active', '1', 'addons');
    adminMenuManager();

    $this->actingAs(Admin::factory()->create(), 'admin')
        ->get(route('admin.config.addons.modules'))
        ->assertOk()
        ->assertSee('href="'.route('admin.ksef.index').'"', false)
        ->assertSee('KSeF');
});

it('ignores malformed entries', function () {
    writeAdminMenuAddon('ZzAdminMenuInvalid', "return [
        ['location' => 'billing', 'label' => 'Good', 'url' => '/admin/good'],
        ['location' => 'billing', 'label' => 'No URL'],
        ['location' => 'nonsense', 'label' => 'Bad location', 'url' => '/admin/no'],
    ];");
    Setting::set('addon_zzadminmenuinvalid_active', '1', 'addons');

    expect(adminMenuManager()->getAdminMenuItems()['billing'])->toBe([
        ['label' => 'Good', 'url' => '/admin/good', 'children' => []],
    ]);
});
