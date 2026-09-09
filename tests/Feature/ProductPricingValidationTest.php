<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Currency;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;

/*
 * Prices went from the form straight into the pricing table without being
 * looked at. A word in the price box answered with a database error, and
 * nothing said which figures a price may be.
 */

function pricingAdmin(): Admin
{
    return Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);
}

test('a price that is not a number is refused when creating a product', function () {
    $currency = Currency::getDefault() ?? Currency::factory()->create(['is_default' => true]);
    $group = ProductGroup::factory()->create();

    $this->actingAs(pricingAdmin(), 'admin')->post(route('admin.products.store'), [
        'name' => 'Odd', 'group_id' => $group->id, 'type' => 'hosting', 'pay_type' => 'recurring',
        'pricing' => [$currency->id => ['monthly' => 'ten']],
    ])->assertSessionHasErrors("pricing.{$currency->id}.monthly");

    expect(Product::where('name', 'Odd')->exists())->toBeFalse();
});

test('a price that is not a number is refused when updating a product, and the old price stays', function () {
    $currency = Currency::getDefault() ?? Currency::factory()->create(['is_default' => true]);
    $group = ProductGroup::factory()->create();
    $product = Product::factory()->create(['group_id' => $group->id]);
    Pricing::create(['type' => 'product', 'currency_id' => $currency->id, 'rel_id' => $product->id, 'monthly' => 10]);

    $this->actingAs(pricingAdmin(), 'admin')->put(route('admin.products.update', $product), [
        'name' => $product->name, 'group_id' => $group->id, 'type' => 'hosting', 'pay_type' => 'recurring',
        'pricing' => [$currency->id => ['monthly' => 'lots']],
    ])->assertSessionHasErrors("pricing.{$currency->id}.monthly");

    expect((float) Pricing::where('rel_id', $product->id)->where('currency_id', $currency->id)->value('monthly'))->toBe(10.0);
});

test('a negative price other than the unpriced marker is refused', function () {
    $currency = Currency::getDefault() ?? Currency::factory()->create(['is_default' => true]);
    $group = ProductGroup::factory()->create();

    $this->actingAs(pricingAdmin(), 'admin')->post(route('admin.products.store'), [
        'name' => 'Negative', 'group_id' => $group->id, 'type' => 'hosting', 'pay_type' => 'recurring',
        'pricing' => [$currency->id => ['monthly' => '-5']],
    ])->assertSessionHasErrors("pricing.{$currency->id}.monthly");
});
