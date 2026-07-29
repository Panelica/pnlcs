<?php

use App\Models\Admin;
use App\Models\BannedEmail;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Models\User;
use App\Services\FraudDetectionService;
use Illuminate\Support\Facades\Mail;

/**
 * Screening orders.
 *
 * Banning an email did nothing at all. The rule that checks the ban list asked
 * for a column that is not on the table, so the evaluation threw, and nothing
 * ran it on the path customers order through in the first place — it was
 * reachable only from an API endpoint that reports a number and acts on
 * nothing.
 */
function fraudShop(string $email): array
{
    $currency = Currency::where('is_default', true)->first()
        ?? Currency::create(['code' => 'USD', 'prefix' => '$', 'suffix' => '', 'rate' => 1, 'is_default' => true]);

    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'server_type' => null,
        'auto_setup' => 'payment',
        'tax' => false,
        'hidden' => false,
        'retired' => false,
    ]);
    Pricing::updateOrCreate(
        ['type' => 'product', 'rel_id' => $product->id, 'currency_id' => $currency->id],
        ['monthly' => 25]
    );

    $user = User::factory()->create(['email' => $email]);
    $client = Client::factory()->create(['email' => $email, 'tax_exempt' => true]);
    $user->clients()->attach($client->id);

    return compact('product', 'client', 'user');
}

test('the fraud check runs at all', function () {
    $fx = fraudShop('someone@example.com');

    $order = Order::create([
        'order_num' => 'FRAUD1',
        'client_id' => $fx['client']->id,
        'date' => now(),
        'amount' => 25,
        'payment_method' => 'banktransfer',
        'status' => 'pending',
        'ip_address' => '1.2.3.4',
    ]);

    $result = app(FraudDetectionService::class)->evaluate($order);

    expect($result['score'])->toBe(0)
        ->and($result['risk_level'])->toBe('low');
});

test('a banned address scores as high risk', function () {
    BannedEmail::create(['domain' => 'crook@spam.test', 'reason' => 'chargebacks']);
    $fx = fraudShop('crook@spam.test');

    $order = Order::create([
        'order_num' => 'FRAUD2', 'client_id' => $fx['client']->id, 'date' => now(),
        'amount' => 25, 'payment_method' => 'banktransfer', 'status' => 'pending', 'ip_address' => '1.2.3.4',
    ]);

    $result = app(FraudDetectionService::class)->evaluate($order);

    expect($result['score'])->toBeGreaterThanOrEqual(60)
        ->and($result['risk_level'])->toBe('high');
});

test('a banned domain catches every address on it', function () {
    BannedEmail::create(['domain' => 'spam.test', 'reason' => 'throwaway domain']);
    $fx = fraudShop('anyone@spam.test');

    $order = Order::create([
        'order_num' => 'FRAUD3', 'client_id' => $fx['client']->id, 'date' => now(),
        'amount' => 25, 'payment_method' => 'banktransfer', 'status' => 'pending', 'ip_address' => '1.2.3.4',
    ]);

    expect(app(FraudDetectionService::class)->evaluate($order)['risk_level'])->toBe('high');
});

test('an order from a banned customer is held, not provisioned', function () {
    Mail::fake();
    BannedEmail::create(['domain' => 'spam.test', 'reason' => 'throwaway domain']);
    $fx = fraudShop('buyer@spam.test');
    $admin = Admin::factory()->create();

    $this->actingAs($fx['user'])->post(route('client.cart.add'), [
        'product_id' => $fx['product']->id,
        'billing_cycle' => 'monthly',
        'domain' => 'held.com',
    ])->assertSessionHasNoErrors();

    $this->actingAs($fx['user'])->post(route('client.cart.process'), [
        'payment_method' => 'banktransfer', 'terms' => '1',
    ])->assertRedirect();

    $order = Order::where('client_id', $fx['client']->id)->firstOrFail();

    expect($order->status)->toBe('fraud');

    // Paying for a held order is not a reason to hand over an account.
    $this->actingAs($admin, 'admin')
        ->post(route('admin.invoices.mark-paid', Invoice::findOrFail($order->invoice_id)), ['gateway' => 'banktransfer'])
        ->assertRedirect();

    expect($order->fresh()->status)->toBe('fraud')
        ->and(Service::where('order_id', $order->id)->first()?->status)->toBe('pending');
});

test('an ordinary customer orders exactly as before', function () {
    Mail::fake();
    $fx = fraudShop('honest@example.com');
    $admin = Admin::factory()->create();

    $this->actingAs($fx['user'])->post(route('client.cart.add'), [
        'product_id' => $fx['product']->id,
        'billing_cycle' => 'monthly',
        'domain' => 'fine.com',
    ])->assertSessionHasNoErrors();

    $this->actingAs($fx['user'])->post(route('client.cart.process'), [
        'payment_method' => 'banktransfer', 'terms' => '1',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $order = Order::where('client_id', $fx['client']->id)->firstOrFail();

    expect($order->status)->toBe('pending');

    $this->actingAs($admin, 'admin')
        ->post(route('admin.invoices.mark-paid', Invoice::findOrFail($order->invoice_id)), ['gateway' => 'banktransfer'])
        ->assertRedirect();

    expect(Service::where('order_id', $order->id)->firstOrFail()->status)->toBe('active');
});
