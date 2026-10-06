<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Service;

/**
 * Sizes are stored in megabytes; people read them in the unit that fits.
 * The admin service page printed "1,997 MB / 0 MB" and hid the bandwidth
 * card altogether when the plan had no limit, however much had been used.
 */
test('a size reads in the largest unit that fits', function (mixed $mb, string $shown) {
    expect(mb_fmt($mb))->toBe($shown);
})->with([
    [0, '0 MB'],
    [512, '512 MB'],
    [1023, '1023 MB'],
    [1024, '1 GB'],
    [1536, '1.5 GB'],
    [1997, '2 GB'],
    [1048576, '1 TB'],
    [2097152, '2 TB'],
    ['2048', '2 GB'],
    [null, '0 MB'],
    [-5, '0 MB'],
]);

test('usage on an unlimited plan is shown, against no limit', function () {
    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);
    $service = Service::factory()->create([
        'disk_limit' => 0, 'disk_usage' => 1536,
        'bw_limit' => 0, 'bw_usage' => 3072,
    ]);

    $this->actingAs($admin, 'admin')
        ->get(route('admin.services.show', $service))
        ->assertOk()
        ->assertSee('1.5 GB / ∞')
        ->assertSee('3 GB / ∞');
});

test('usage within a limit is shown against it', function () {
    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);
    $service = Service::factory()->create([
        'disk_limit' => 2048, 'disk_usage' => 512,
        'bw_limit' => 10240, 'bw_usage' => 0,
    ]);

    $this->actingAs($admin, 'admin')
        ->get(route('admin.services.show', $service))
        ->assertOk()
        ->assertSee('512 MB / 2 GB')
        ->assertSee('0 MB / 10 GB');
});
