<?php

use App\Models\Admin;
use App\Models\Setting;
use App\Services\AddonManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/*
 * Addons declare admin menu entries through sidebar(), and AddonManager had a
 * getSidebarItems() collecting them, but nothing ever called it: no addon's
 * entry reached the admin navigation, not even the bundled Project Management
 * and Staff Board ones. Active addons' entries now appear in an "Extensions"
 * menu of the admin top navigation.
 *
 * Each test writes a real addon folder and removes it afterwards.
 */

// One folder per test: PHP keeps the first class it loaded under a name for the rest of the run.
const ZZ_MENU_DIRS = ['modules/Addons/ZzMenu', 'modules/Addons/ZzMenuOff', 'modules/Addons/ZzMenuBoom', 'modules/Addons/ZzMenuNext', 'modules/Addons/ZzMenuShape'];

function writeZzMenuAddon(string $folder, string $name, string $sidebarBody): void
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
    public function getDisplayName(): string { return '{$folder}'; }
    public function getDescription(): string { return 'Test fixture.'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getAuthor(): string { return 'Tests'; }
    public function activate(): array { return ['success' => true]; }
    public function deactivate(): array { return ['success' => true]; }
    public function output(Request \$request): string { return ''; }
    public function sidebar(): array { {$sidebarBody} }
    public function config(): array { return []; }
    public function upgrade(string \$fromVersion): array { return ['success' => true]; }
}
PHP);
}

/** A manager that sees the fixture folders written by the test. */
function zzMenuManager(): AddonManager
{
    $manager = (new AddonManager())->discover();
    app()->instance(AddonManager::class, $manager);

    return $manager;
}

function zzMenuAdminHtml(): string
{
    return test()->actingAs(Admin::factory()->create(), 'admin')
        ->get(route('admin.config.addons.modules'))
        ->assertOk()
        ->getContent();
}

afterEach(function () {
    foreach (ZZ_MENU_DIRS as $dir) {
        File::deleteDirectory(base_path($dir));
    }
});

it('shows an active addon\'s menu entries in the admin navigation', function () {
    writeZzMenuAddon('ZzMenu', 'zzmenu', "return [['label' => 'Zz Board', 'url' => '/admin/zz-board', 'children' => [
        ['label' => 'Zz Board', 'url' => '/admin/zz-board'],
        ['label' => 'Zz New Post', 'url' => '/admin/zz-board/new'],
    ]]];");
    Setting::set('addon_zzmenu_active', '1', 'addons');
    zzMenuManager();

    $html = zzMenuAdminHtml();

    expect($html)->toContain('href="/admin/zz-board">Zz Board</a>')
        ->toContain('href="/admin/zz-board/new"')
        ->toContain('Zz New Post')
        // the child that repeats its parent's link is not listed twice
        ->and(substr_count($html, 'href="/admin/zz-board"'))->toBe(1);
});

it('leaves out the entries of an addon that is switched off', function () {
    writeZzMenuAddon('ZzMenuOff', 'zzmenuoff', "return [['label' => 'Zz Hidden', 'url' => '/admin/zz-hidden']];");
    zzMenuManager();

    expect(zzMenuAdminHtml())->not->toContain('Zz Hidden');
});

it('skips an addon whose sidebar() throws, and the admin page still renders', function () {
    writeZzMenuAddon('ZzMenuBoom', 'zzmenuboom', "throw new \\RuntimeException('boom');");
    writeZzMenuAddon('ZzMenuNext', 'zzmenunext', "return [['label' => 'Zz Next', 'url' => '/admin/zz-next']];");
    Setting::set('addon_zzmenuboom_active', '1', 'addons');
    Setting::set('addon_zzmenunext_active', '1', 'addons');
    zzMenuManager();

    expect(zzMenuAdminHtml())->toContain('Zz Next');
});

it('drops entries without a label or a url', function () {
    writeZzMenuAddon('ZzMenuShape', 'zzmenushape', "return [['label' => 'Zz Board'], ['url' => '/admin/x'], 'nonsense', ['label' => 'Zz Ok', 'url' => '/admin/zz-ok', 'children' => [['label' => 'no url']]]];");
    Setting::set('addon_zzmenushape_active', '1', 'addons');

    expect(zzMenuManager()->getSidebarItems())->toBe([
        ['label' => 'Zz Ok', 'url' => '/admin/zz-ok', 'children' => []],
    ]);
});

it('gives the bundled addons menu entries that lead to a page', function () {
    // Staff Board pointed at /admin/addons/staffboard, a route that never existed; the menu was never shown, so
    // nobody noticed. Its screen is the addon page under /admin/config/addons/modules/staffboard.
    Setting::set('addon_staffboard_active', '1', 'addons');
    Setting::set('addon_project_management_active', '1', 'addons');

    $routes = app('router')->getRoutes();
    $dead = [];
    $items = zzMenuManager()->getSidebarItems();
    expect(array_column($items, 'label'))->toContain('Projects', 'Staff Board');
    foreach ($items as $item) {
        foreach (array_merge([$item], $item['children']) as $entry) {
            try {
                $routes->match(Request::create($entry['url'], 'GET'));
            } catch (NotFoundHttpException) {
                $dead[] = $entry['url'];
            }
        }
    }

    expect($dead)->toBe([]);
});
