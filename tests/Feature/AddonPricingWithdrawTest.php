<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\ProductAddon;

/**
 * The addon form said "leave a cycle at zero to not offer the addon on that
 * cycle", but 0 sells it free there; -1 is what withdraws a cycle
 * (ProductAddon::offeredOn), and the inputs had min="0", so it could not be
 * entered.
 */
function addonAdmin(): Admin
{
    return Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);
}

it('saves -1 as a cycle the addon is not offered on, and 0 as free', function () {
    \App\Models\Currency::where('is_default', true)->exists()
        || \App\Models\Currency::create(['code' => 'USD', 'prefix' => '$', 'suffix' => '', 'rate' => 1, 'is_default' => true]);

    $this->actingAs(addonAdmin(), 'admin')->post(route('admin.config.addons.store'), [
        'name' => 'Backups',
        'pricing' => ['monthly' => '2.50', 'quarterly' => '0', 'semiannually' => '-1', 'annually' => '-5', 'biennially' => '', 'triennially' => '30'],
    ])->assertSessionHasNoErrors();

    $addon = ProductAddon::where('name', 'Backups')->firstOrFail();

    expect($addon->offeredOn('monthly'))->toBeTrue()
        ->and($addon->offeredOn('quarterly'))->toBeTrue()
        ->and($addon->offeredOn('semiannually'))->toBeFalse()
        ->and($addon->offeredOn('annually'))->toBeFalse()
        ->and((float) $addon->pricing()->first()->annually)->toBe(-1.0);
});

it('lets the form take -1 and says what 0 and -1 mean', function () {
    $page = $this->actingAs(addonAdmin(), 'admin')->get(route('admin.config.addons'))->assertOk()->getContent();

    expect($page)->toContain('min="-1" name="pricing[monthly]"')
        ->and($page)->not->toContain('min="0" name="pricing[')
        ->and($page)->toContain(e(__('admin.addons.pricing_hint')));
});
