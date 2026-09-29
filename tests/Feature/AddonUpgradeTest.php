<?php

use App\Models\Admin;
use App\Models\Setting;
use App\Services\AddonManager;
use Illuminate\Support\Facades\File;

/*
 * AddonModuleInterface has upgrade(string $fromVersion) and activate() records
 * the addon's version, but nothing compared the two or called upgrade(): an
 * addon whose new version needs a table change or a settings migration never
 * got the chance to run it. An active addon whose files carry a newer version
 * is now upgraded when its admin screens are next opened (as WHMCS does), and
 * one that was off is upgraded before it is switched back on.
 *
 * Each test writes a real addon folder under its own name (PHP keeps the first
 * class it loaded under a name for the rest of the run) and removes it after.
 */

const ZZ_UPGRADE_DIRS = ['modules/Addons/ZzUpOk', 'modules/Addons/ZzUpFail', 'modules/Addons/ZzUpThrow', 'modules/Addons/ZzUpOff',
    'modules/Addons/ZzUpSame', 'modules/Addons/ZzUpOlder'];

/** $upgradeBody runs inside upgrade(); every call is counted in the setting "<name>_upgrade_calls". */
function writeZzUpgradeAddon(string $folder, string $version, string $upgradeBody = "return ['success' => true];"): string
{
    $name = strtolower($folder);
    $dir = base_path('modules/Addons/'.$folder);
    File::ensureDirectoryExists($dir);
    File::put($dir."/{$folder}Module.php", <<<PHP
<?php

namespace Modules\Addons\\{$folder};

use App\Contracts\AddonModuleInterface;
use App\Models\Setting;
use Illuminate\Http\Request;

class {$folder}Module implements AddonModuleInterface
{
    public function getName(): string { return '{$name}'; }
    public function getDisplayName(): string { return '{$folder}'; }
    public function getDescription(): string { return 'Test fixture.'; }
    public function getVersion(): string { return '{$version}'; }
    public function getAuthor(): string { return 'Tests'; }
    public function activate(): array { return ['success' => true, 'message' => 'on']; }
    public function deactivate(): array { return ['success' => true, 'message' => 'off']; }
    public function output(Request \$request): string { return ''; }
    public function sidebar(): array { return []; }
    public function config(): array { return []; }
    public function upgrade(string \$fromVersion): array
    {
        Setting::set('{$name}_upgrade_calls', (string) ((int) Setting::get('{$name}_upgrade_calls', 0) + 1), 'addons');
        Setting::set('{$name}_upgraded_from', \$fromVersion, 'addons');
        {$upgradeBody}
    }
}
PHP);
    app()->instance(AddonManager::class, (new AddonManager())->discover());

    return $name;
}

/** The addon as the database knows it: switched on (or off) at an older version. */
function recordZzAddon(string $name, string $version, bool $active = true): void
{
    Setting::set("addon_{$name}_active", $active ? '1' : '0', 'addons');
    Setting::set("addon_{$name}_version", $version, 'addons');
}

function openExtensionsPage()
{
    return test()->actingAs(Admin::factory()->create(), 'admin')->get(route('admin.config.addons.modules'));
}

afterEach(function () {
    foreach (ZZ_UPGRADE_DIRS as $dir) {
        File::deleteDirectory(base_path($dir));
    }
});

it('upgrades an active addon from the recorded version once its files are newer', function () {
    $name = writeZzUpgradeAddon('ZzUpOk', '1.1.0');
    recordZzAddon($name, '1.0.0');

    openExtensionsPage()->assertOk()->assertSee('ZzUpOk was upgraded to version 1.1.0.');

    expect(Setting::get("{$name}_upgraded_from"))->toBe('1.0.0')
        ->and(Setting::get("addon_{$name}_version"))->toBe('1.1.0');

    // done once: the next visit has nothing to upgrade
    openExtensionsPage()->assertOk()->assertDontSee('was upgraded');
    expect(Setting::get("{$name}_upgrade_calls"))->toBe('1');
});

it('keeps the old version when the upgrade fails, and tries again next time', function () {
    $name = writeZzUpgradeAddon('ZzUpFail', '2.0.0', "return ['success' => false, 'message' => 'table is locked'];");
    recordZzAddon($name, '1.0.0');

    openExtensionsPage()->assertOk()->assertSee('ZzUpFail could not be upgraded and will be tried again next time: table is locked');
    expect(Setting::get("addon_{$name}_version"))->toBe('1.0.0');

    openExtensionsPage()->assertOk();
    expect(Setting::get("{$name}_upgrade_calls"))->toBe('2');
});

it('treats an upgrade that throws as failed, and the page still opens', function () {
    $name = writeZzUpgradeAddon('ZzUpThrow', '1.1.0', "throw new \\RuntimeException('boom');");
    recordZzAddon($name, '1.0.0');

    openExtensionsPage()->assertOk()->assertSee('boom');
    expect(Setting::get("addon_{$name}_version"))->toBe('1.0.0');
});

it('upgrades an addon that was off before switching it back on', function () {
    $name = writeZzUpgradeAddon('ZzUpOff', '1.2.0');
    recordZzAddon($name, '1.0.0', active: false);

    // an inactive addon is left alone while it is off
    openExtensionsPage()->assertOk();
    expect(Setting::get("{$name}_upgrade_calls"))->toBeNull();

    test()->actingAs(Admin::factory()->create(), 'admin')
        ->post(route('admin.config.addons.modules.toggle', $name))
        ->assertRedirect();

    expect(Setting::get("{$name}_upgraded_from"))->toBe('1.0.0')
        ->and(Setting::get("addon_{$name}_version"))->toBe('1.2.0')
        ->and(app(AddonManager::class)->isActive($name))->toBeTrue();
});

it('does not switch an addon back on when its upgrade fails', function () {
    $name = writeZzUpgradeAddon('ZzUpFail', '2.0.0', "return ['success' => false, 'message' => 'table is locked'];");
    recordZzAddon($name, '1.0.0', active: false);

    $result = app(AddonManager::class)->activate($name);

    expect($result['success'])->toBeFalse()
        ->and(app(AddonManager::class)->isActive($name))->toBeFalse()
        ->and(Setting::get("addon_{$name}_version"))->toBe('1.0.0');
});

it('leaves an addon alone when its files are the recorded version or older', function () {
    $same = writeZzUpgradeAddon('ZzUpSame', '1.0.0');
    recordZzAddon($same, '1.0.0');
    $older = writeZzUpgradeAddon('ZzUpOlder', '1.0.0');
    recordZzAddon($older, '1.5.0');

    openExtensionsPage()->assertOk();

    expect(Setting::get("{$same}_upgrade_calls"))->toBeNull()
        ->and(Setting::get("{$older}_upgrade_calls"))->toBeNull()
        ->and(Setting::get("addon_{$older}_version"))->toBe('1.5.0');
});
