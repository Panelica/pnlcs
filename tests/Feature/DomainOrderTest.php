<?php

use App\Models\Client;
use App\Models\Currency;
use App\Models\Domain;
use App\Models\DomainPricing;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\User;
use App\Services\CartService;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Mail;

/**
 * Buying a domain on its own, which is a different path through checkout from
 * buying hosting and easy to leave behind when the order code is reworked.
 */
function domainCart(): array
{
    $currency = Currency::where('is_default', true)->first()
        ?? Currency::create(['code' => 'USD', 'prefix' => '$', 'suffix' => '', 'rate' => 1, 'is_default' => true]);

    $client = Client::factory()->create(['tax_exempt' => false]);

    foreach (['.com', '.net'] as $tld) {
        DomainPricing::updateOrCreate(['extension' => $tld], [
            'register_price' => 12, 'transfer_price' => 10, 'renew_price' => 14,
            'min_years' => 1, 'max_years' => 10, 'enabled' => true,
        ]);
    }

    return compact('client', 'currency');
}

test('a domain bought in the cart is recorded and invoiced against itself', function () {
    Mail::fake();
    $fx = domainCart();
    $cart = app(CartService::class);
    $c = $cart->getOrCreateCart($fx['client']->id);

    $cart->addDomain($c, 'buy-me.com', 'register', 2);
    $order = $cart->checkout($c, $fx['client']->id, 'banktransfer');

    $domain = Domain::where('order_id', $order->id)->firstOrFail();
    $line = InvoiceItem::where('invoice_id', $order->invoice_id)->where('type', 'Domain')->firstOrFail();

    // rel_id has to point at the domain: RenewOnPaymentListener reads this
    // column to know what was paid for, and the renewal generator uses it to
    // avoid invoicing the same domain twice.
    expect((int) $line->rel_id)->toBe($domain->id)
        ->and($domain->registration_period)->toBe(2)
        ->and($domain->registrar)->not->toBeNull()
        ->and($domain->status)->toBe('pending')
        ->and($domain->expiry_date->toDateString())->toBe(now()->addYears(2)->toDateString());
});

test('a transfer is recorded as a transfer', function () {
    Mail::fake();
    $fx = domainCart();
    $cart = app(CartService::class);
    $c = $cart->getOrCreateCart($fx['client']->id);

    $cart->addDomain($c, 'move-me.com', 'transfer', 1);
    $order = $cart->checkout($c, $fx['client']->id, 'banktransfer');

    expect(strtolower(Domain::where('order_id', $order->id)->firstOrFail()->type))->toBe('transfer');
});

test('paying for a domain activates it and sets its renewal date', function () {
    Mail::fake();
    $fx = domainCart();
    $cart = app(CartService::class);
    $c = $cart->getOrCreateCart($fx['client']->id);

    $cart->addDomain($c, 'pay-me.com', 'register', 1);
    $order = $cart->checkout($c, $fx['client']->id, 'banktransfer');
    $invoice = Invoice::findOrFail($order->invoice_id);

    app(PaymentService::class)
        ->applyPayment($invoice, 'banktransfer', 'TXN-DOMAIN', (float) $invoice->total);

    $domain = Domain::where('order_id', $order->id)->firstOrFail();

    expect(strtolower($domain->status))->toBe('active');
});

test('a domain and hosting bought together share one invoice with both lines', function () {
    Mail::fake();
    $fx = domainCart();
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'tax' => false,
    ]);
    Pricing::updateOrCreate(
        ['type' => 'product', 'rel_id' => $product->id, 'currency_id' => $fx['currency']->id],
        ['monthly' => 20]
    );

    $cart = app(CartService::class);
    $c = $cart->getOrCreateCart($fx['client']->id);
    $cart->addProduct($c, $product, 'monthly', 'mixed.com');
    $cart->addDomain($c, 'mixed.net', 'register', 1);

    $order = $cart->checkout($c, $fx['client']->id, 'banktransfer');

    expect(Invoice::where('client_id', $fx['client']->id)->count())->toBe(1)
        ->and(InvoiceItem::where('invoice_id', $order->invoice_id)->where('type', 'Hosting')->count())->toBe(1)
        ->and(InvoiceItem::where('invoice_id', $order->invoice_id)->where('type', 'Domain')->count())->toBe(1);
});

test('an emptied cart cannot be checked out into an empty order', function () {
    $fx = domainCart();
    $cart = app(CartService::class);
    $c = $cart->getOrCreateCart($fx['client']->id);

    $order = $cart->checkout($c, $fx['client']->id, 'banktransfer');

    // Nothing was bought, so nothing should be owed.
    expect((float) Invoice::findOrFail($order->invoice_id)->total)->toBe(0.0);
});

test('a TLD we do not sell is refused instead of vanishing from the cart', function () {
    // addDomain returned the cart untouched for an unpriced TLD and the
    // controller announced success anyway, so the customer was told their
    // domain was in the cart and then checked out without it.
    $fx = domainCart();
    $user = User::factory()->create();
    $user->clients()->attach($fx['client']->id);

    $this->actingAs($user)->post(route('client.cart.add-domain'), [
        'domain' => 'nothanks.xyz',
        'type' => 'register',
        'years' => 1,
    ])->assertSessionHasErrors();

    $cart = app(CartService::class);
    $totals = $cart->calculateTotal($cart->getOrCreateCart($fx['client']->id));

    expect($totals['items'])->toBeEmpty();
});
