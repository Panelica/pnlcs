<?php

use App\Models\Currency;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductTerm;
use App\Models\Service;
use App\Services\BillingCycleHelper;
use Carbon\Carbon;

/*
 * A term of months no billing cycle is named after - seven months, say.
 *
 * Panelica Central sells a licence for any term of 1 to 12 months. PNLCS could
 * sell four of them, because a product's price lives in a column named after
 * its cycle and there are six such columns. A term with no name now has its
 * price in product_terms, and everything that reads a cycle - the price, the
 * next due date, the period on the invoice - reads the months out of it.
 */

function termCurrency(): Currency
{
    return Currency::firstOrCreate(
        ['code' => 'USD'],
        ['prefix' => '$', 'suffix' => '', 'format' => 1, 'rate' => 1.0, 'is_default' => true],
    );
}

test('the months of a term are read from its name', function () {
    expect(BillingCycleHelper::months('monthly'))->toBe(1)
        ->and(BillingCycleHelper::months('Semi-Annually'))->toBe(6)
        ->and(BillingCycleHelper::months('triennially'))->toBe(36)
        ->and(BillingCycleHelper::months('7months'))->toBe(7)
        ->and(BillingCycleHelper::months('1months'))->toBe(1)
        ->and(BillingCycleHelper::months('12months'))->toBe(12)
        // Central sells no longer term, so nothing here may name one.
        ->and(BillingCycleHelper::months('13months'))->toBeNull()
        ->and(BillingCycleHelper::months('0months'))->toBeNull()
        ->and(BillingCycleHelper::months('weekly'))->toBeNull();
});

test('a term of months is named after its length, and the four with names keep them', function () {
    expect(BillingCycleHelper::cycleForMonths(1))->toBe('monthly')
        ->and(BillingCycleHelper::cycleForMonths(3))->toBe('quarterly')
        ->and(BillingCycleHelper::cycleForMonths(6))->toBe('semiannually')
        ->and(BillingCycleHelper::cycleForMonths(12))->toBe('annually')
        ->and(BillingCycleHelper::cycleForMonths(7))->toBe('7months')
        ->and(BillingCycleHelper::cycleForMonths(2))->toBe('2months');
});

test('a date advances by the months of the term', function () {
    $from = Carbon::parse('2026-01-31');

    expect(BillingCycleHelper::advance($from, '7months')->toDateString())->toBe('2026-08-31')
        // No overflow, as for the named cycles: the anniversary stays where it
        // was sold rather than creeping into the next month.
        ->and(BillingCycleHelper::advance(Carbon::parse('2026-01-31'), '1months')->toDateString())->toBe('2026-02-28')
        ->and(BillingCycleHelper::advance($from, 'monthly')->toDateString())->toBe('2026-02-28');
});

test('a service on a term of months knows how long its period is', function () {
    expect(Service::monthsInCycle('7months'))->toBe(7)
        ->and(Service::monthsInCycle('annually'))->toBe(12)
        // Unknown still counts as one month, as it did before there was a map.
        ->and(Service::monthsInCycle('weekly'))->toBe(1);
});

test('the price of a term with no name comes from product_terms', function () {
    $usd = termCurrency();
    $product = Product::factory()->create(['type' => 'hosting', 'pay_type' => 'recurring']);
    ProductTerm::create(['product_id' => $product->id, 'currency_id' => $usd->id, 'months' => 7, 'price' => 70.0, 'setup' => 5.0]);

    expect($product->priceFor('7months', $usd->id))->toBe(70.0)
        ->and($product->setupFeeFor('7months', $usd->id))->toBe(5.0)
        // A term nobody priced is not sold, exactly as an empty column is not.
        ->and($product->priceFor('5months', $usd->id))->toBeNull();
});

test('a product offers every term it is sold on, shortest first, named or not', function () {
    $usd = termCurrency();
    $product = Product::factory()->create(['type' => 'hosting', 'pay_type' => 'recurring']);
    Pricing::create(['type' => 'product', 'currency_id' => $usd->id, 'rel_id' => $product->id, 'monthly' => 10, 'annually' => 100, 'quarterly' => -1]);
    ProductTerm::create(['product_id' => $product->id, 'currency_id' => $usd->id, 'months' => 7, 'price' => 70.0]);

    expect(array_keys($product->pricedCycles($usd->id)))->toBe(['monthly', '7months', 'annually']);
});

test('a term of months can be ordered, and is priced, dated and invoiced by its length', function () {
    $usd = termCurrency();
    $product = Product::factory()->create(['type' => 'hosting', 'pay_type' => 'recurring', 'auto_setup' => 'manual', 'hidden' => false, 'retired' => false]);
    Pricing::create(['type' => 'product', 'currency_id' => $usd->id, 'rel_id' => $product->id, 'monthly' => 10]);
    ProductTerm::create(['product_id' => $product->id, 'currency_id' => $usd->id, 'months' => 7, 'price' => 63.0]);

    $user = \App\Models\User::factory()->create();
    $user->clients()->attach(\App\Models\Client::factory()->create()->id);

    // The cart takes the term the same way it takes a named cycle.
    $this->actingAs($user)->post(route('client.cart.add'), [
        'product_id' => $product->id, 'billing_cycle' => '7months',
        'domain' => 'seven-months.test', 'domain_option' => 'own',
    ])->assertSessionHasNoErrors();

    $data = \App\Models\Cart::query()->latest('id')->value('data');
    $data = is_string($data) ? json_decode($data, true) : (array) $data;
    $item = collect($data['items'] ?? [])->first();
    expect($item)->not->toBeNull()
        ->and((float) ($item['price'] ?? 0))->toBe(63.0)
        ->and($item['billing_cycle'] ?? null)->toBe('7months');
});

test('a term longer than Central sells is refused', function () {
    $usd = termCurrency();
    $product = Product::factory()->create(['type' => 'hosting', 'pay_type' => 'recurring']);
    Pricing::create(['type' => 'product', 'currency_id' => $usd->id, 'rel_id' => $product->id, 'monthly' => 10]);
    $user = \App\Models\User::factory()->create();
    $user->clients()->attach(\App\Models\Client::factory()->create()->id);

    $this->actingAs($user)->post(route('client.cart.add'), [
        'product_id' => $product->id, 'billing_cycle' => '13months',
    ])->assertSessionHasErrors('billing_cycle');
});

test('a service ordered for seven months is due in seven months, and renews by seven', function () {
    $usd = termCurrency();
    $product = Product::factory()->create(['type' => 'hosting', 'pay_type' => 'recurring', 'auto_setup' => 'manual']);
    Pricing::create(['type' => 'product', 'currency_id' => $usd->id, 'rel_id' => $product->id, 'monthly' => 10]);
    ProductTerm::create(['product_id' => $product->id, 'currency_id' => $usd->id, 'months' => 7, 'price' => 63.0]);
    $client = \App\Models\Client::factory()->create();

    Carbon::setTestNow('2026-01-15 10:00:00');

    $order = app(\App\Services\OrderService::class)->processOrder($client, [[
        'type' => 'product', 'product_id' => $product->id, 'billing_cycle' => '7months',
        'amount' => 63.0, 'domain' => 'seven.test',
    ]], 'banktransfer');

    $service = Service::where('order_id', $order->id)->firstOrFail();
    expect($service->billing_cycle)->toBe('7months')
        ->and((string) $service->next_due_date)->toContain('2026-08-15');

    // Paying the renewal moves the date on by the term, not by a month.
    $service->update(['status' => 'active', 'next_due_date' => '2026-08-15']);
    expect(BillingCycleHelper::advance(Carbon::parse($service->next_due_date), $service->billing_cycle)->toDateString())
        ->toBe('2027-03-15');

    Carbon::setTestNow();
});
