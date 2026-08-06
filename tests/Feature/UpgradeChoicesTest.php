<?php

use App\Models\Client;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Models\User;

/**
 * The upgrade screen quotes somebody else's price.
 *
 * It lists every active package and works the price out in the view, by
 * walking monthly, quarterly, semiannually, annually and taking the first one
 * with a figure in it. The customer's own billing term is never consulted.
 *
 * So a customer on an annual service is shown the monthly price of every
 * package, and packages that are not sold on the annual term at all are
 * offered to them - they choose one and are told, after choosing, that it is
 * not available for their billing cycle.
 */
function productPricedAt(array $prices, string $name = 'Upgrade target'): Product
{
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'name' => $name,
        'hidden' => false,
        'retired' => false,
    ]);

    Pricing::factory()->create(array_merge(
        ['type' => 'product', 'rel_id' => $product->id],
        $prices
    ));

    return $product;
}

function upgradeScreenFor(string $cycle, float $currentAmount = 10.0): array
{
    $client = Client::factory()->create();
    $user = User::factory()->create();
    $user->clients()->attach($client->id);

    $current = productPricedAt(['monthly' => $currentAmount, 'annually' => $currentAmount * 10], 'Current plan');

    $service = Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => $current->id,
        'billing_cycle' => $cycle,
        'amount' => $currentAmount,
        'status' => 'active',
        'next_due_date' => now()->addDays(20),
    ]);

    return [$user, $service];
}

it('does not offer a package that is not sold on the customers term', function () {
    [$user, $service] = upgradeScreenFor('Annually');

    productPricedAt(['monthly' => 30, 'annually' => -1], 'Monthly only');

    $html = test()->actingAs($user)
        ->get(route('client.services.upgrade', $service))
        ->assertOk()
        ->getContent();

    expect($html)->not->toContain('Monthly only');
});

it('quotes the price for the term the customer is on', function () {
    [$user, $service] = upgradeScreenFor('Annually');

    productPricedAt(['monthly' => 30, 'annually' => 300], 'Bigger plan');

    $html = test()->actingAs($user)
        ->get(route('client.services.upgrade', $service))
        ->assertOk()
        ->getContent();

    expect($html)->toContain(money_fmt(300));
    expect($html)->not->toContain(money_fmt(30));
});

it('still offers a package sold on that term', function () {
    [$user, $service] = upgradeScreenFor('Annually');

    productPricedAt(['monthly' => 30, 'annually' => 300], 'Bigger plan');

    $html = test()->actingAs($user)
        ->get(route('client.services.upgrade', $service))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('Bigger plan');
});

it('says there is nothing to move to rather than breaking', function () {
    [$user, $service] = upgradeScreenFor('Annually');

    productPricedAt(['monthly' => 30, 'annually' => -1], 'Monthly only');

    test()->actingAs($user)
        ->get(route('client.services.upgrade', $service))
        ->assertOk()
        ->assertSee(__('client.services.no_upgrades'), false);
});
