<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Currency;
use App\Models\TaxRule;
use App\Models\Promotion;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ===== HELPERS =====

function makeBillingAdmin(array $attrs = []): Admin
{
    $role = AdminRole::factory()->fullAdmin()->create();
    return Admin::factory()->create(array_merge(['role_id' => $role->id], $attrs));
}

// ===== CURRENCIES =====

test('admin can view currencies page', function () {
    $admin = makeBillingAdmin();
    $response = $this->actingAs($admin, 'admin')->get(route('admin.config.currencies'));
    $response->assertStatus(200)->assertSee('Currencies');
});

test('currencies page lists existing currencies', function () {
    $admin = makeBillingAdmin();
    Currency::factory()->create(['code' => 'USD', 'prefix' => '$']);
    Currency::factory()->create(['code' => 'EUR', 'prefix' => '€']);

    $response = $this->actingAs($admin, 'admin')->get(route('admin.config.currencies'));
    $response->assertSee('USD')->assertSee('EUR');
});

test('admin can add a currency', function () {
    $admin = makeBillingAdmin();

    $response = $this->actingAs($admin, 'admin')->post(route('admin.config.currencies.store'), [
        'code'   => 'GBP',
        'prefix' => '£',
        'suffix' => '',
        'rate'   => 0.79,
    ]);

    $response->assertRedirect()->assertSessionHas('success');
    $this->assertDatabaseHas('currencies', ['code' => 'GBP', 'prefix' => '£']);
});

test('add currency requires code and rate', function () {
    $admin = makeBillingAdmin();
    $response = $this->actingAs($admin, 'admin')->post(route('admin.config.currencies.store'), []);
    $response->assertSessionHasErrors(['code', 'rate']);
});

test('add currency enforces unique code', function () {
    $admin = makeBillingAdmin();
    Currency::factory()->create(['code' => 'USD']);

    $response = $this->actingAs($admin, 'admin')->post(route('admin.config.currencies.store'), [
        'code' => 'USD',
        'rate' => 1.0,
    ]);

    $response->assertSessionHasErrors('code');
});

test('admin can update a currency', function () {
    $admin = makeBillingAdmin();
    $currency = Currency::factory()->create(['code' => 'USD', 'rate' => 1.0]);

    $response = $this->actingAs($admin, 'admin')->put(route('admin.config.currencies.update', $currency), [
        'code'   => 'USD',
        'prefix' => '$',
        'rate'   => 1.05,
    ]);

    $response->assertRedirect()->assertSessionHas('success');
    $currency->refresh();
    expect((float) $currency->rate)->toBe(1.05);
});

test('admin can set a default currency', function () {
    $admin = makeBillingAdmin();
    $first = Currency::factory()->create(['code' => 'USD', 'is_default' => true]);
    $second = Currency::factory()->create(['code' => 'EUR', 'is_default' => false]);

    $this->actingAs($admin, 'admin')->post(route('admin.config.currencies.default', $second));

    $first->refresh();
    $second->refresh();
    expect($first->is_default)->toBeFalse();
    expect($second->is_default)->toBeTrue();
});

test('admin can delete a non-default currency', function () {
    $admin = makeBillingAdmin();
    $currency = Currency::factory()->create(['code' => 'GBP', 'is_default' => false]);

    $response = $this->actingAs($admin, 'admin')->delete(route('admin.config.currencies.destroy', $currency));

    $response->assertRedirect()->assertSessionHas('success');
    $this->assertDatabaseMissing('currencies', ['id' => $currency->id]);
});

test('admin cannot delete the default currency', function () {
    $admin = makeBillingAdmin();
    $currency = Currency::factory()->create(['code' => 'USD', 'is_default' => true]);

    $response = $this->actingAs($admin, 'admin')->delete(route('admin.config.currencies.destroy', $currency));

    $response->assertRedirect()->assertSessionHas('error');
    $this->assertDatabaseHas('currencies', ['id' => $currency->id]);
});

// ===== TAX RULES =====

test('admin can view tax rules page', function () {
    $admin = makeBillingAdmin();
    $response = $this->actingAs($admin, 'admin')->get(route('admin.config.tax'));
    $response->assertStatus(200)->assertSee('Tax Rules');
});

test('tax page lists existing rules', function () {
    $admin = makeBillingAdmin();
    TaxRule::factory()->create(['name' => 'US Sales Tax', 'tax_rate' => 8.5]);

    $response = $this->actingAs($admin, 'admin')->get(route('admin.config.tax'));
    $response->assertSee('US Sales Tax');
});

test('admin can create a tax rule', function () {
    $admin = makeBillingAdmin();

    $response = $this->actingAs($admin, 'admin')->post(route('admin.config.tax.store'), [
        'name'     => 'VAT',
        'tax_rate' => 20.0,
        'country'  => 'GB',
        'level'    => 1,
    ]);

    $response->assertRedirect()->assertSessionHas('success');
    $this->assertDatabaseHas('tax_rules', ['name' => 'VAT', 'country' => 'GB']);
});

test('create tax rule requires name and rate', function () {
    $admin = makeBillingAdmin();
    $response = $this->actingAs($admin, 'admin')->post(route('admin.config.tax.store'), []);
    $response->assertSessionHasErrors(['name', 'tax_rate']);
});

test('tax rate must be between 0 and 100', function () {
    $admin = makeBillingAdmin();

    $this->actingAs($admin, 'admin')->post(route('admin.config.tax.store'), [
        'name'     => 'Bad Tax',
        'tax_rate' => 150,
    ])->assertSessionHasErrors('tax_rate');

    $this->actingAs($admin, 'admin')->post(route('admin.config.tax.store'), [
        'name'     => 'Bad Tax',
        'tax_rate' => -5,
    ])->assertSessionHasErrors('tax_rate');
});

test('admin can update a tax rule', function () {
    $admin = makeBillingAdmin();
    $rule = TaxRule::factory()->create(['name' => 'Old Tax', 'tax_rate' => 5.0]);

    $response = $this->actingAs($admin, 'admin')->put(route('admin.config.tax.update', $rule), [
        'name'     => 'New Tax',
        'tax_rate' => 10.0,
    ]);

    $response->assertRedirect()->assertSessionHas('success');
    $rule->refresh();
    expect($rule->name)->toBe('New Tax');
    expect((float) $rule->tax_rate)->toBe(10.0);
});

test('admin can delete a tax rule', function () {
    $admin = makeBillingAdmin();
    $rule = TaxRule::factory()->create();

    $response = $this->actingAs($admin, 'admin')->delete(route('admin.config.tax.destroy', $rule));

    $response->assertRedirect()->assertSessionHas('success');
    $this->assertDatabaseMissing('tax_rules', ['id' => $rule->id]);
});

// ===== PROMOTIONS =====

test('admin can view promotions page', function () {
    $admin = makeBillingAdmin();
    $response = $this->actingAs($admin, 'admin')->get(route('admin.config.promotions'));
    $response->assertStatus(200)->assertSee('Promotions');
});

test('promotions page lists existing promotions', function () {
    $admin = makeBillingAdmin();
    Promotion::factory()->create(['code' => 'SUMMER25', 'type' => 'percentage', 'value' => 25]);

    $response = $this->actingAs($admin, 'admin')->get(route('admin.config.promotions'));
    $response->assertSee('SUMMER25');
});

test('admin can create a promotion', function () {
    $admin = makeBillingAdmin();

    $response = $this->actingAs($admin, 'admin')->post(route('admin.config.promotions.store'), [
        'code'      => 'LAUNCH10',
        'type'      => 'percentage',
        'value'     => 10.0,
        'max_uses'  => 100,
        'recurring' => false,
    ]);

    $response->assertRedirect()->assertSessionHas('success');
    $this->assertDatabaseHas('promotions', ['code' => 'LAUNCH10', 'type' => 'percentage']);
});

test('create promotion requires code, type, and value', function () {
    $admin = makeBillingAdmin();
    $response = $this->actingAs($admin, 'admin')->post(route('admin.config.promotions.store'), []);
    $response->assertSessionHasErrors(['code', 'type', 'value']);
});

test('create promotion enforces unique code', function () {
    $admin = makeBillingAdmin();
    Promotion::factory()->create(['code' => 'EXISTING']);

    $response = $this->actingAs($admin, 'admin')->post(route('admin.config.promotions.store'), [
        'code'  => 'EXISTING',
        'type'  => 'percentage',
        'value' => 10,
    ]);

    $response->assertSessionHasErrors('code');
});

test('promotion type must be valid', function () {
    $admin = makeBillingAdmin();

    $response = $this->actingAs($admin, 'admin')->post(route('admin.config.promotions.store'), [
        'code'  => 'BADTYPE',
        'type'  => 'invalid_type',
        'value' => 10,
    ]);

    $response->assertSessionHasErrors('type');
});

test('admin can update a promotion', function () {
    $admin = makeBillingAdmin();
    $promo = Promotion::factory()->create(['code' => 'OLD', 'type' => 'percentage', 'value' => 5.0]);

    $response = $this->actingAs($admin, 'admin')->put(route('admin.config.promotions.update', $promo), [
        'code'      => 'UPDATED',
        'type'      => 'percentage',
        'value'     => 15.0,
        'recurring' => false,
    ]);

    $response->assertRedirect()->assertSessionHas('success');
    $promo->refresh();
    expect($promo->code)->toBe('UPDATED');
    expect((float) $promo->value)->toBe(15.0);
});

test('admin can delete a promotion', function () {
    $admin = makeBillingAdmin();
    $promo = Promotion::factory()->create();

    $response = $this->actingAs($admin, 'admin')->delete(route('admin.config.promotions.destroy', $promo));

    $response->assertRedirect()->assertSessionHas('success');
    $this->assertDatabaseMissing('promotions', ['id' => $promo->id]);
});

test('unauthenticated user cannot access billing config pages', function () {
    $this->get(route('admin.config.currencies'))->assertRedirect(route('admin.login'));
    $this->get(route('admin.config.tax'))->assertRedirect(route('admin.login'));
    $this->get(route('admin.config.promotions'))->assertRedirect(route('admin.login'));
});

test('expiry date must be after start date', function () {
    $admin = makeBillingAdmin();

    $response = $this->actingAs($admin, 'admin')->post(route('admin.config.promotions.store'), [
        'code'            => 'BADDATE',
        'type'            => 'percentage',
        'value'           => 10,
        'start_date'      => '2026-12-01',
        'expiration_date' => '2026-01-01',
    ]);

    $response->assertSessionHasErrors('expiration_date');
});
