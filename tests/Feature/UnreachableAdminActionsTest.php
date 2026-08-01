<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\ClientGroup;
use App\Models\Currency;

/**
 * Two controls the admin screens never really offered.
 *
 * The client groups page has an Edit button that calls openModal on a modal
 * that is not rendered anywhere - clicking it does nothing at all - so a
 * group's name, colour and discount could be set once and never corrected.
 * The discount is the one that matters: it comes off every invoice for
 * everybody in the group.
 *
 * The currencies page shows which currency is the default and gives no way to
 * change it. Everything is priced in that currency, and since the gateways
 * were taught to charge in it, it decides what the customer is billed in too.
 */
function configAdmin(): Admin
{
    return Admin::factory()->create([
        'role_id' => AdminRole::factory()->fullAdmin()->create()->id,
    ]);
}

test('the edit button on a client group opens something that exists', function () {
    $group = ClientGroup::create(['name' => 'Resellers', 'color' => '#405189', 'discount_percent' => 10]);

    $html = $this->actingAs(configAdmin(), 'admin')
        ->get(route('admin.config.client-groups'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain("openModal('edit-group-{$group->id}')")
        ->and($html)->toContain("modal-edit-group-{$group->id}")
        ->and($html)->toContain(route('admin.config.client-groups.update', $group));
});

test('a client group can be corrected', function () {
    $group = ClientGroup::create(['name' => 'Resellers', 'color' => '#405189', 'discount_percent' => 10]);

    $this->actingAs(configAdmin(), 'admin')
        ->put(route('admin.config.client-groups.update', $group), [
            'name' => 'Resellers',
            'color' => '#112233',
            'discount_percent' => 15,
        ])->assertRedirect();

    expect((float) $group->fresh()->discount_percent)->toBe(15.0);
});

test('the currencies page offers a way to change the default', function () {
    $other = Currency::firstOrCreate(['code' => 'GBP'], ['prefix' => '£', 'suffix' => '', 'rate' => 1, 'is_default' => false]);

    $this->actingAs(configAdmin(), 'admin')
        ->get(route('admin.config.currencies'))
        ->assertOk()
        ->assertSee(route('admin.config.currencies.default', $other), false);
});

test('choosing a default moves it off the old one', function () {
    Currency::query()->update(['is_default' => false]);
    $old = Currency::firstOrCreate(['code' => 'USD'], ['prefix' => '$', 'suffix' => '', 'rate' => 1]);
    $old->update(['is_default' => true]);
    $new = Currency::firstOrCreate(['code' => 'TRY'], ['prefix' => '₺', 'suffix' => '', 'rate' => 40]);

    $this->actingAs(configAdmin(), 'admin')
        ->post(route('admin.config.currencies.default', $new))
        ->assertRedirect();

    expect((bool) $new->fresh()->is_default)->toBeTrue()
        ->and((bool) $old->fresh()->is_default)->toBeFalse();
});
