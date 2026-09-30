<?php

use App\Models\Admin;
use App\Models\Setting;
use App\Services\AddonManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

/*
 * AddonModuleInterface has upgrade(string $fromVersion) and activate() records
 * the addon's version, but nothing compared the two or called upgrade(): an
 * addon whose new version needs a table change or a settings migration never
 * got the chance to run it. `php artisan pnlcs:addons-upgrade`, a step of the
 * update sequence, now upgrades every active addon whose files carry a newer
 * version, one run per addon at a time; one that was off is upgraded before it
 * is switched back on. The Extensions screen only shows what is pending.
 *
 * Each test writes a real addon folder under its own name (PHP keeps the first
 * class it loaded under a name for the rest of the run) and removes it after.
 */

const ZZ_UPGRADE_DIRS = ['modules/Addons/ZzUpOk', 'modules/Addons/ZzUpFail', 'modules/Addons/ZzUpThrow', 'modules/Addons/ZzUpOff',
    'modules/Addons/ZzUpSame', 'modules/Addons/ZzUpOlder', 'modules/Addons/ZzUpShown', 'modules/Addons/ZzUpLocked'];

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

    test()->artisan('pnlcs:addons-upgrade')
        ->expectsOutput("{$name}: upgraded from 1.0.0 to 1.1.0.")
        ->assertExitCode(0);

    expect(Setting::get("{$name}_upgraded_from"))->toBe('1.0.0')
        ->and(Setting::get("addon_{$name}_version"))->toBe('1.1.0');

    // done once: the next run has nothing to upgrade
    test()->artisan('pnlcs:addons-upgrade')->expectsOutput('No addon upgrades pending.')->assertExitCode(0);
    expect(Setting::get("{$name}_upgrade_calls"))->toBe('1');
});

it('keeps the old version when the upgrade fails, exits non-zero, and tries again next run', function () {
    $name = writeZzUpgradeAddon('ZzUpFail', '2.0.0', "return ['success' => false, 'message' => 'table is locked'];");
    recordZzAddon($name, '1.0.0');

    test()->artisan('pnlcs:addons-upgrade')
        ->expectsOutput("{$name}: upgrade from 1.0.0 to 2.0.0 failed: table is locked")
        ->assertExitCode(1);
    expect(Setting::get("addon_{$name}_version"))->toBe('1.0.0');

    test()->artisan('pnlcs:addons-upgrade')->assertExitCode(1);
    expect(Setting::get("{$name}_upgrade_calls"))->toBe('2');
});

it('treats an upgrade that throws as failed', function () {
    $name = writeZzUpgradeAddon('ZzUpThrow', '1.1.0', "throw new \\RuntimeException('boom');");
    recordZzAddon($name, '1.0.0');

    test()->artisan('pnlcs:addons-upgrade')->expectsOutput("{$name}: upgrade from 1.0.0 to 1.1.0 failed: boom")->assertExitCode(1);
    expect(Setting::get("addon_{$name}_version"))->toBe('1.0.0');
});

it('skips an addon whose upgrade is already running, and upgrades it once the lock is free', function () {
    $name = writeZzUpgradeAddon('ZzUpLocked', '1.1.0');
    recordZzAddon($name, '1.0.0');

    $held = Cache::lock("addon-upgrade:{$name}", 600);
    expect($held->get())->toBeTrue();

    test()->artisan('pnlcs:addons-upgrade')
        ->expectsOutput("{$name}: skipped, another upgrade of this addon is running.")
        ->assertExitCode(0);
    expect(Setting::get("{$name}_upgrade_calls"))->toBeNull()
        ->and(Setting::get("addon_{$name}_version"))->toBe('1.0.0');

    $held->release();

    test()->artisan('pnlcs:addons-upgrade')->assertExitCode(0);
    expect(Setting::get("{$name}_upgrade_calls"))->toBe('1')
        ->and(Setting::get("addon_{$name}_version"))->toBe('1.1.0');
});

it('only shows a pending upgrade on the Extensions screen, without running it', function () {
    $name = writeZzUpgradeAddon('ZzUpShown', '1.1.0');
    recordZzAddon($name, '1.0.0');

    openExtensionsPage()->assertOk()
        ->assertSee('Version 1.1.0 is installed but its upgrade from 1.0.0 has not run yet.')
        ->assertSee('php artisan pnlcs:addons-upgrade');

    expect(Setting::get("{$name}_upgrade_calls"))->toBeNull()
        ->and(Setting::get("addon_{$name}_version"))->toBe('1.0.0');
});

it('upgrades an addon that was off before switching it back on', function () {
    $name = writeZzUpgradeAddon('ZzUpOff', '1.2.0');
    recordZzAddon($name, '1.0.0', active: false);

    // an inactive addon is left alone while it is off
    test()->artisan('pnlcs:addons-upgrade')->assertExitCode(0);
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

    test()->artisan('pnlcs:addons-upgrade')->expectsOutput('No addon upgrades pending.')->assertExitCode(0);

    expect(Setting::get("{$same}_upgrade_calls"))->toBeNull()
        ->and(Setting::get("{$older}_upgrade_calls"))->toBeNull()
        ->and(Setting::get("addon_{$older}_version"))->toBe('1.5.0');
});
