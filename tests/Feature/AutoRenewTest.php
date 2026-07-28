<?php

use App\Models\Client;
use App\Models\Currency;
use App\Models\Domain;
use App\Models\Invoice;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductAddon;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Models\ServiceAddon;
use App\Models\User;

/**
 * Turning renewal off.
 *
 * The customer has a switch for this on both services and domains. Nothing
 * read either one: the renewal generator billed regardless, the invoice then
 * went overdue, and the account was suspended for not paying for something the
 * customer had explicitly said they did not want to keep.
 */
function autoRenewFixture(): Product
{
    $currency = Currency::where('is_default', true)->first()
        ?? Currency::create(['code' => 'USD', 'prefix' => '$', 'suffix' => '', 'rate' => 1, 'is_default' => true]);

    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'tax' => false,
    ]);
    Pricing::updateOrCreate(
        ['type' => 'product', 'rel_id' => $product->id, 'currency_id' => $currency->id],
        ['monthly' => 25]
    );

    return $product;
}

test('a service with renewal switched off is not invoiced', function () {
    $product = autoRenewFixture();
    $client = Client::factory()->create();

    Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'status' => 'active',
        'amount' => 25,
        'billing_cycle' => 'Monthly',
        'next_due_date' => now()->addDays(3),
        'domain' => 'no-renew.com',
        'auto_renew' => false,
    ]);

    $this->artisan('pnlcs:generate-invoices')->assertSuccessful();

    expect(Invoice::where('client_id', $client->id)->count())->toBe(0);
});

test('a service with renewal left on is invoiced as before', function () {
    $product = autoRenewFixture();
    $client = Client::factory()->create();

    Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'status' => 'active',
        'amount' => 25,
        'billing_cycle' => 'Monthly',
        'next_due_date' => now()->addDays(3),
        'domain' => 'yes-renew.com',
        'auto_renew' => true,
    ]);

    $this->artisan('pnlcs:generate-invoices')->assertSuccessful();

    expect(Invoice::where('client_id', $client->id)->count())->toBe(1);
});

test('a domain with renewal switched off is not invoiced', function () {
    $client = Client::factory()->create();

    // The customer's switch flips payment_method to none; there is no separate
    // column for it.
    Domain::factory()->create([
        'client_id' => $client->id,
        'status' => 'active',
        'domain' => 'no-renew.net',
        'recurring_amount' => 12,
        'registration_period' => 1,
        'next_due_date' => now()->addDays(3),
        'payment_method' => 'none',
    ]);

    $this->artisan('pnlcs:generate-invoices')->assertSuccessful();

    expect(Invoice::where('client_id', $client->id)->count())->toBe(0);
});

test('a domain with renewal left on is invoiced as before', function () {
    $client = Client::factory()->create();

    Domain::factory()->create([
        'client_id' => $client->id,
        'status' => 'active',
        'domain' => 'yes-renew.net',
        'recurring_amount' => 12,
        'registration_period' => 1,
        'next_due_date' => now()->addDays(3),
        'payment_method' => 'banktransfer',
    ]);

    $this->artisan('pnlcs:generate-invoices')->assertSuccessful();

    expect(Invoice::where('client_id', $client->id)->count())->toBe(1);
});

test('the switch the customer flips is the one the biller reads', function () {
    $product = autoRenewFixture();
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);

    $service = Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'status' => 'active',
        'amount' => 25,
        'billing_cycle' => 'Monthly',
        'next_due_date' => now()->addDays(3),
        'domain' => 'toggle.com',
        'auto_renew' => true,
    ]);

    $this->actingAs($user)->post(route('client.services.autorenew', $service))->assertRedirect();

    expect($service->fresh()->auto_renew)->toBeFalse();

    $this->artisan('pnlcs:generate-invoices')->assertSuccessful();

    expect(Invoice::where('client_id', $client->id)->count())->toBe(0);
});

test('an addon on a service that is not renewing is not billed either', function () {
    $product = autoRenewFixture();
    $client = Client::factory()->create();

    $service = Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'status' => 'active',
        'amount' => 25,
        'billing_cycle' => 'Monthly',
        'next_due_date' => now()->addMonths(6),
        'domain' => 'addon-no-renew.com',
        'auto_renew' => false,
    ]);

    $addon = ProductAddon::create([
        'name' => 'Dedicated IP', 'packages' => null,
        'hidden' => false, 'retired' => false, 'sort_order' => 1, 'tax' => false,
    ]);
    ServiceAddon::create([
        'service_id' => $service->id,
        'addon_id' => $addon->id,
        'client_id' => $client->id,
        'qty' => 1,
        'amount' => 5,
        'billing_cycle' => 'Monthly',
        'next_due_date' => now()->addDays(2),
        'status' => 'active',
    ]);

    $this->artisan('pnlcs:generate-invoices')->assertSuccessful();

    expect(Invoice::where('client_id', $client->id)->count())->toBe(0);
});

test('a domain with no payment method recorded is still billed', function () {
    // 'none' is the customer switching renewal off. A null is just a record
    // that predates the field, and SQL comparisons drop nulls, so this would
    // have stopped billing it without anyone asking.
    $client = Client::factory()->create();

    Domain::factory()->create([
        'client_id' => $client->id,
        'status' => 'active',
        'domain' => 'null-method.net',
        'recurring_amount' => 12,
        'registration_period' => 1,
        'next_due_date' => now()->addDays(3),
        'payment_method' => null,
    ]);

    $this->artisan('pnlcs:generate-invoices')->assertSuccessful();

    expect(Invoice::where('client_id', $client->id)->count())->toBe(1);
});
