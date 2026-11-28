<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Setting;
use App\Services\AddonManager;
use Illuminate\Support\Facades\File;

function writeAdminMenuAddon(string $folder, string $body, string $sidebarBody = 'return [];'): void
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
    public function sidebar(): array { {$sidebarBody} }
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
    'modules/Addons/ZzAdminMenuAll',
    'modules/Addons/ZzAdminMenuBoth',
    'modules/Addons/ZzAdminMenuRoute',
    'modules/Addons/ZzAdminMenuPermission',
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

it('renders KSeF in Billing through the admin menu provider, not Extensions', function () {
    Setting::set('addon_ksef_active', '1', 'addons');
    $manager = adminMenuManager();

    $this->actingAs(Admin::factory()->create(), 'admin')
        ->get(route('admin.config.addons.modules'))
        ->assertOk()
        ->assertSee('href="'.route('admin.ksef.index').'"', false)
        ->assertSee('KSeF');

    $items = $manager->getAdminMenuItems();

    expect($items)->toHaveKey('billing')
        ->and($items)->not->toHaveKey('extensions')
        ->and($items['billing'])->toContain([
            'label' => 'KSeF',
            'url' => route('admin.ksef.index'),
            'children' => [],
        ]);
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

it('publishes links into every main menu location', function () {
    writeAdminMenuAddon('ZzAdminMenuAll', "return [
        ['location' => 'clients', 'label' => 'Zz Clients', 'url' => '/admin/zz-clients'],
        ['location' => 'orders', 'label' => 'Zz Orders', 'url' => '/admin/zz-orders'],
        ['location' => 'billing', 'label' => 'Zz Billing', 'url' => '/admin/zz-billing'],
        ['location' => 'support', 'label' => 'Zz Support', 'url' => '/admin/zz-support'],
        ['location' => 'utilities', 'label' => 'Zz Utilities', 'url' => '/admin/zz-utilities'],
        ['location' => 'extensions', 'label' => 'Zz Extensions', 'url' => '/admin/zz-extensions'],
    ];");
    Setting::set('addon_zzadminmenuall_active', '1', 'addons');
    adminMenuManager();

    $html = $this->actingAs(Admin::factory()->create(), 'admin')
        ->get(route('admin.config.addons.modules'))
        ->assertOk()
        ->getContent();

    foreach (['Zz Clients', 'Zz Orders', 'Zz Billing', 'Zz Support', 'Zz Utilities', 'Zz Extensions'] as $label) {
        expect($html)->toContain($label);
    }
});

it('ignores the legacy sidebar when the addon publishes its own menu', function () {
    writeAdminMenuAddon(
        'ZzAdminMenuBoth',
        "return [['location' => 'billing', 'label' => 'Zz Billing', 'url' => '/admin/zz-billing']];",
        "return [['label' => 'Zz Legacy', 'url' => '/admin/zz-legacy']];",
    );
    Setting::set('addon_zzadminmenuboth_active', '1', 'addons');

    $items = adminMenuManager()->getAdminMenuItems();

    expect($items)->toHaveKey('billing')
        ->and($items)->not->toHaveKey('extensions')
        ->and($items['billing'])->toHaveCount(1);
});

it('resolves a route name and skips one that is not registered', function () {
    writeAdminMenuAddon('ZzAdminMenuRoute', "return [
        ['location' => 'billing', 'label' => 'Zz Dashboard', 'route' => 'admin.dashboard'],
        ['location' => 'billing', 'label' => 'Zz Missing', 'route' => 'admin.route-that-does-not-exist'],
    ];");
    Setting::set('addon_zzadminmenuroute_active', '1', 'addons');

    expect(adminMenuManager()->getAdminMenuItems()['billing'])->toBe([
        ['label' => 'Zz Dashboard', 'url' => route('admin.dashboard'), 'children' => []],
    ]);
});

it('hides a published link without its permission', function () {
    writeAdminMenuAddon(
        'ZzAdminMenuPermission',
        "return [['location' => 'billing', 'label' => 'Zz Billing', 'url' => '/admin/zz-billing', 'permission' => 'manage_invoices']];",
    );
    Setting::set('addon_zzadminmenupermission_active', '1', 'addons');

    $admin = Admin::factory()->create([
        'role_id' => AdminRole::factory()->create(['permissions' => []])->id,
    ]);
    $this->actingAs($admin, 'admin');

    expect(adminMenuManager()->getAdminMenuItems())->toBe([]);
});
