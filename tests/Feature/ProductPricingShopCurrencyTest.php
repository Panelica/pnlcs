<?php

use App\Models\Admin;
use App\Models\Currency;
use App\Models\Pricing;
use App\Models\Product;

/*
 * The product form edits one price, in the shop currency. A customer who chose
 * another currency sees that price converted at the daily rate; a price typed
 * against another currency would not be the one charged, so the form no longer
 * offers those boxes and a save leaves their rows alone.
 */

// Named for this file: the plain name shopCurrency() already belongs to
// BillingCurrencyAndIdentityTest, and declaring it twice is a fatal error that
// stops the whole suite from loading - not just these tests.
function pricingShopCurrency(): Currency
{
    $pln = Currency::firstOrCreate(
        ['code' => 'PLN'],
        ['prefix' => '', 'suffix' => ' zł', 'format' => 1, 'rate' => 1.0, 'is_default' => true],
    );

    if (! $pln->is_default) {
        Currency::where('is_default', true)->update(['is_default' => false]);
        $pln->update(['is_default' => true]);
    }

    return $pln;
}

function pricingOtherCurrency(): Currency
{
    return Currency::firstOrCreate(
        ['code' => 'EUR'],
        ['prefix' => '€', 'suffix' => ' EUR', 'format' => 1, 'rate' => 0.25, 'is_default' => false],
    );
}

test('the product form offers only the shop currency price', function () {
    $default = pricingShopCurrency();
    $other = pricingOtherCurrency();
    $product = Product::factory()->create();
    $admin = Admin::factory()->create();

    foreach ([route('admin.products.edit', $product), route('admin.products.create')] as $url) {
        $html = $this->actingAs($admin, 'admin')->get($url)->assertOk()->getContent();
        expect($html)->toContain('pricing['.$default->id.']')
            ->and($html)->not->toContain('pricing['.$other->id.']');
    }
});

test('saving the price updates the shop row and leaves another currency row alone', function () {
    $default = pricingShopCurrency();
    $other = pricingOtherCurrency();
    $product = Product::factory()->create(['type' => 'hosting']);
    Pricing::create(['type' => 'product', 'currency_id' => $default->id, 'rel_id' => $product->id, 'monthly' => 10]);
    Pricing::create(['type' => 'product', 'currency_id' => $other->id, 'rel_id' => $product->id, 'monthly' => 2.5]);

    $this->actingAs(Admin::factory()->create(), 'admin')->put(route('admin.products.update', $product), [
        'name' => $product->name, 'group_id' => $product->group_id, 'type' => 'hosting', 'pay_type' => 'recurring',
        'pricing' => [$default->id => [
            'monthly' => '12.00', 'quarterly' => '-1', 'semiannually' => '-1',
            'annually' => '-1', 'biennially' => '-1', 'triennially' => '-1',
        ]],
    ])->assertRedirect();

    $row = fn (Currency $currency) => Pricing::where('type', 'product')
        ->where('rel_id', $product->id)->where('currency_id', $currency->id)->value('monthly');

    expect((float) $row($default))->toBe(12.0)
        ->and((float) $row($other))->toBe(2.5);
});
