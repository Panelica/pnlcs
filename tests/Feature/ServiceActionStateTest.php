<?php

use App\Models\CancellationRequest;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Models\Upgrade;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * What a customer can do to a service depends on the state it is in, and the
 * pages that offer these actions already know that — the upgrade page only
 * lists products the shop is selling, the cancel page is only reachable from a
 * live service. The requests behind them checked ownership and nothing else.
 */
function serviceInState(string $status): array
{
    $currency = Currency::where('is_default', true)->first()
        ?? Currency::create(['code' => 'USD', 'prefix' => '$', 'suffix' => '', 'rate' => 1, 'is_default' => true]);

    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);

    $current = Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id, 'tax' => false]);
    Pricing::updateOrCreate(
        ['type' => 'product', 'rel_id' => $current->id, 'currency_id' => $currency->id],
        ['monthly' => 20]
    );

    $service = Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => $current->id,
        'status' => $status,
        'amount' => 20,
        'billing_cycle' => 'Monthly',
        'next_due_date' => now()->addDays(20),
        'domain' => $status.'-service.com',
    ]);

    return compact('user', 'client', 'service', 'currency');
}

function upgradeTarget(array $attributes, Currency $currency): Product
{
    $product = Product::factory()->create(array_merge([
        'group_id' => ProductGroup::factory()->create()->id,
        'tax' => false,
    ], $attributes));

    Pricing::updateOrCreate(
        ['type' => 'product', 'rel_id' => $product->id, 'currency_id' => $currency->id],
        ['monthly' => 60]
    );

    return $product;
}

test('a terminated service cannot be upgraded', function () {
    $fx = serviceInState('terminated');
    $target = upgradeTarget(['hidden' => false, 'retired' => false], $fx['currency']);

    $this->actingAs($fx['user'])->post(route('client.services.upgrade.process', $fx['service']), [
        'new_product_id' => $target->id,
    ])->assertRedirect();

    expect(Upgrade::count())->toBe(0);
});

test('an active service can still be upgraded', function () {
    $fx = serviceInState('active');
    $target = upgradeTarget(['hidden' => false, 'retired' => false], $fx['currency']);

    $this->actingAs($fx['user'])->post(route('client.services.upgrade.process', $fx['service']), [
        'new_product_id' => $target->id,
    ])->assertRedirect();

    expect(Upgrade::count())->toBe(1);
});

test('a service cannot be upgraded onto a product the shop is not selling', function () {
    $fx = serviceInState('active');
    $hidden = upgradeTarget(['hidden' => true, 'retired' => false], $fx['currency']);

    $this->actingAs($fx['user'])->post(route('client.services.upgrade.process', $fx['service']), [
        'new_product_id' => $hidden->id,
    ])->assertRedirect();

    expect(Upgrade::count())->toBe(0);
});

test('a terminated service cannot be cancelled again', function () {
    Mail::fake();
    $fx = serviceInState('terminated');

    $this->actingAs($fx['user'])->post(route('client.services.cancel.submit', $fx['service']), [
        'type' => 'Immediate',
        'reason' => 'Already gone',
    ])->assertRedirect();

    expect(CancellationRequest::count())->toBe(0);
});

test('the same service cannot be queued for cancellation twice', function () {
    Mail::fake();
    $fx = serviceInState('active');

    $this->actingAs($fx['user'])->post(route('client.services.cancel.submit', $fx['service']), [
        'type' => 'End of Billing Period',
        'reason' => 'Moving on',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $this->actingAs($fx['user'])->post(route('client.services.cancel.submit', $fx['service']), [
        'type' => 'Immediate',
        'reason' => 'Actually now',
    ])->assertRedirect();

    expect(CancellationRequest::where('service_id', $fx['service']->id)->count())->toBe(1);
});

test('an active service can still be cancelled', function () {
    Mail::fake();
    $fx = serviceInState('active');

    $this->actingAs($fx['user'])->post(route('client.services.cancel.submit', $fx['service']), [
        'type' => 'Immediate',
        'reason' => 'No longer needed',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(CancellationRequest::where('service_id', $fx['service']->id)->count())->toBe(1);
});
