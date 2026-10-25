<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Promotion;

/*
 * Billing honours a promotion's number of cycles and its start date, but the
 * promotion screen had no field for either, and the "Add" form had no
 * "applies to renewals" switch: a code could not be made recurring, limited to
 * "the first three months", or set to start next week, without editing the
 * database.
 */

function pcsAdmin(): Admin
{
    return Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);
}

test('a new promotion can recur for a number of cycles and start on a date', function () {
    $this->actingAs(pcsAdmin(), 'admin')->post(route('admin.config.promotions.store'), [
        'code' => 'FIRST3', 'type' => 'percentage', 'value' => 50,
        'recurring' => '1', 'cycles' => 3, 'start_date' => '2026-11-01', 'expiration_date' => '2026-11-30',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $promo = Promotion::where('code', 'FIRST3')->sole();
    expect($promo->recurring)->toBeTrue()
        ->and((int) $promo->cycles)->toBe(3)
        ->and($promo->start_date->format('Y-m-d'))->toBe('2026-11-01');
});

test('editing changes the cycles and the start date, and an empty cycles field means no limit', function () {
    $promo = Promotion::create(['code' => 'KEEP', 'type' => 'percentage', 'value' => 10, 'recurring' => true, 'cycles' => 2]);

    $this->actingAs(pcsAdmin(), 'admin')->put(route('admin.config.promotions.update', $promo), [
        'code' => 'KEEP', 'type' => 'percentage', 'value' => 10, 'recurring' => '1', 'cycles' => 6, 'start_date' => '2026-12-01',
    ])->assertSessionHasNoErrors();
    expect((int) $promo->fresh()->cycles)->toBe(6)->and($promo->fresh()->start_date->format('Y-m-d'))->toBe('2026-12-01');

    $this->actingAs(pcsAdmin(), 'admin')->put(route('admin.config.promotions.update', $promo), [
        'code' => 'KEEP', 'type' => 'percentage', 'value' => 10, 'recurring' => '1', 'cycles' => '', 'start_date' => '',
    ])->assertSessionHasNoErrors();
    expect((int) $promo->fresh()->cycles)->toBe(0)->and($promo->fresh()->start_date)->toBeNull();
});

test('cycles cannot be negative, and the expiry cannot come before the start', function () {
    $this->actingAs(pcsAdmin(), 'admin')->post(route('admin.config.promotions.store'), [
        'code' => 'BAD', 'type' => 'percentage', 'value' => 5, 'cycles' => -1, 'start_date' => '2026-11-10', 'expiration_date' => '2026-11-01',
    ])->assertSessionHasErrors(['cycles', 'expiration_date']);

    expect(Promotion::where('code', 'BAD')->exists())->toBeFalse();
});

test('both forms show the fields, filled in on the edit form', function () {
    Promotion::create(['code' => 'SHOWN', 'type' => 'percentage', 'value' => 10, 'recurring' => true, 'cycles' => 4, 'start_date' => '2026-11-05']);

    $html = $this->actingAs(pcsAdmin(), 'admin')->get(route('admin.config.promotions'))->assertOk()->getContent();

    expect(substr_count($html, 'name="cycles"'))->toBe(2)
        ->and(substr_count($html, 'name="start_date"'))->toBe(2)
        ->and(substr_count($html, 'name="recurring"'))->toBe(2)
        ->and($html)->toContain('name="cycles" value="4"')->toContain('value="2026-11-05"')
        ->toContain(e(__('admin.promotions.cycles')));
});
