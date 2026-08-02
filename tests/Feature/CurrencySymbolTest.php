<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Currency;

/**
 * The currency sign on the screens.
 *
 * Ninety-seven amounts across the panel were printed as a dollar sign followed
 * by a number, whatever currency the shop sells in. That was harmless while
 * the default could not be changed; it cannot stay that way now that it can be,
 * and the gateways charge in it.
 *
 * money_fmt() has been there all along and knows the prefix and the suffix.
 */
test('no screen writes a currency sign of its own', function () {
    $offenders = [];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views')));

    foreach ($files as $file) {
        $path = (string) $file;

        if (! str_ends_with($path, '.blade.php')) {
            continue;
        }

        foreach (file($path) as $n => $line) {
            // A hardcoded sign immediately before a formatted amount.
            if (preg_match('/[$€£₺]\s*\{\{\s*number_format/', $line)) {
                $offenders[] = str_replace(resource_path('views').'/', '', $path).':'.($n + 1);
            }
        }
    }

    expect($offenders)->toBe([]);
});

test('an amount is shown in the currency the shop sells in', function () {
    Currency::query()->update(['is_default' => false]);
    Currency::updateOrCreate(
        ['code' => 'TRY'],
        ['prefix' => '₺', 'suffix' => '', 'rate' => 1, 'is_default' => true]
    );
    app()->forgetInstance('pnlcs.currency');

    $client = Client::factory()->create(['credit' => 250]);

    $admin = Admin::factory()->create([
        'role_id' => AdminRole::factory()->fullAdmin()->create()->id,
    ]);

    $this->actingAs($admin, 'admin')
        ->get(route('admin.clients.show', $client))
        ->assertOk()
        ->assertSee('₺250.00', false)
        ->assertDontSee('$250.00', false);
});
