<?php

use App\Models\Setting;
use Database\Seeders\DatabaseSeeder;

/**
 * The installer runs DatabaseSeeder, which seeded the Domain setting as
 * hosting.panelica.com. Domain is the address console-generated links use
 * (AppServiceProvider::useConfiguredDomainForConsoleLinks), so on a fresh
 * install every reminder, invoice and suspension mail sent by the scheduler
 * linked to that host until the operator found the field. Left empty, those
 * links follow APP_URL, which the installer writes.
 */
it('seeds no address of its own for the shop', function () {
    Setting::where('setting', 'Domain')->delete();

    $this->seed(DatabaseSeeder::class);

    expect(trim((string) Setting::get('Domain', '')))->toBe('');
});
