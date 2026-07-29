<?php

use App\Models\Client;
use App\Models\ConfigOption;
use App\Models\ConfigOptionGroup;
use App\Models\ConfigOptionSub;
use App\Models\Currency;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Models\User;
use App\Services\CartService;
use App\Services\UpgradeService;

/**
 * Prices, and the currency they are in.
 *
 * Every product carries a price row per currency, but the code asked for them
 * with pricing->first() — the lowest id, which happens to be the dollar row.
 * The symbol beside the number came from the default currency instead. Sell in
 * anything but dollars and the shop advertised one number and the cart charged
 * a different one.
 *
 * The same rows use -1 to mean "not sold on this cycle". Upgrades read it as a
 * price, so moving to a package that is not offered monthly set the monthly
 * amount to minus one.
 */
function currencyPair(): array
{
    Currency::query()->update(['is_default' => false]);

    $usd = Currency::updateOrCreate(
        ['code' => 'USD'],
        ['prefix' => '$', 'suffix' => '', 'rate' => 1, 'is_default' => false]
    );

    $eur = Currency::updateOrCreate(
        ['code' => 'EUR'],
        ['prefix' => '€', 'suffix' => '', 'rate' => 0.9, 'is_default' => true]
    );

    return [$usd, $eur];
}

function pricedProduct(array $usdRow, array $eurRow, ?Currency $usd = null, ?Currency $eur = null): Product
{
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create(['hidden' => false])->id,
        'hidden' => false,
        'retired' => false,
    ]);

    foreach ([[$usd, $usdRow], [$eur, $eurRow]] as [$currency, $row]) {
        Pricing::create(array_merge(
            ['type' => 'product', 'rel_id' => $product->id, 'currency_id' => $currency->id],
            $row
        ));
    }

    return $product;
}

test('the shop lists the price the cart will actually charge', function () {
    [$usd, $eur] = currencyPair();

    $product = pricedProduct(
        ['monthly' => 2.99, 'annually' => 29.99],
        ['monthly' => 2.75, 'annually' => 27.50],
        $usd,
        $eur
    );

    $user = User::factory()->create();
    $user->clients()->attach(Client::factory()->create()->id);

    $this->actingAs($user)->get(route('client.store'))
        ->assertOk()
        ->assertSee('€2.75')
        ->assertDontSee('2.99');

    expect(app(CartService::class)->getProductPrice($product, 'monthly'))->toBe(2.75);
});

test('the configure page prices in the currency being sold in', function () {
    [$usd, $eur] = currencyPair();

    $product = pricedProduct(
        ['monthly' => 2.99, 'annually' => 29.99],
        ['monthly' => 2.75, 'annually' => 27.50],
        $usd,
        $eur
    );

    $user = User::factory()->create();
    $user->clients()->attach(Client::factory()->create()->id);

    $this->actingAs($user)->get(route('client.store.configure', $product->slug))
        ->assertOk()
        ->assertSee('€2.75')
        ->assertDontSee('$2.99');
});

test('a cycle the product is not sold on is not a price', function () {
    [$usd, $eur] = currencyPair();

    // Sold by the year only — the monthly column carries the not-available
    // marker the seeder and the admin screen both write.
    $product = pricedProduct(
        ['monthly' => -1, 'annually' => 120],
        ['monthly' => -1, 'annually' => 110],
        $usd,
        $eur
    );

    expect(app(CartService::class)->getProductPrice($product, 'monthly'))->toBe(0.0)
        ->and(app(CartService::class)->getProductPrice($product, 'annually'))->toBe(110.0);
});

test('upgrading to a package not sold on the cycle is refused, not priced at minus one', function () {
    [$usd, $eur] = currencyPair();

    $target = pricedProduct(
        ['monthly' => -1, 'annually' => 120],
        ['monthly' => -1, 'annually' => 110],
        $usd,
        $eur
    );

    $client = Client::factory()->create();
    $service = Service::factory()->create([
        'client_id' => $client->id,
        'billing_cycle' => 'Monthly',
        'amount' => 10,
        'status' => 'active',
        'next_due_date' => now()->addDays(15),
    ]);

    expect(app(UpgradeService::class)->calculateProration($service, $target))
        ->toMatchArray(['available' => false]);
});

test('an upgrade is priced in the currency being sold in', function () {
    [$usd, $eur] = currencyPair();

    $target = pricedProduct(
        ['monthly' => 30],
        ['monthly' => 20],
        $usd,
        $eur
    );

    $client = Client::factory()->create();
    $service = Service::factory()->create([
        'client_id' => $client->id,
        'billing_cycle' => 'Monthly',
        'amount' => 10,
        'status' => 'active',
        'next_due_date' => now()->addDays(15),
    ]);

    $quote = app(UpgradeService::class)->calculateProration($service, $target);

    expect($quote['available'])->toBeTrue()
        ->and($quote['new_recurring'] ?? null)->toBe(20.0);
});

test('a configurable option is priced in the currency being sold in', function () {
    [$usd, $eur] = currencyPair();

    $product = pricedProduct(['monthly' => 5], ['monthly' => 4], $usd, $eur);

    $group = ConfigOptionGroup::create(['name' => 'Extras', 'description' => '']);
    $option = ConfigOption::create([
        'group_id' => $group->id,
        'option_name' => 'Extra RAM',
        'option_type' => 1,
        'sort_order' => 0,
        'hidden' => false,
    ]);
    $sub = ConfigOptionSub::create([
        'config_id' => $option->id,
        'option_name' => '2 GB',
        'sort_order' => 0,
        'hidden' => false,
    ]);

    foreach ([[$usd, 9.0], [$eur, 6.0]] as [$currency, $price]) {
        Pricing::create([
            'type' => ConfigOptionSub::PRICING_TYPE,
            'rel_id' => $sub->id,
            'currency_id' => $currency->id,
            'monthly' => $price,
        ]);
    }

    expect($sub->priceFor('monthly'))->toBe(6.0);
});
