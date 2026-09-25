<?php

use App\Models\Cart;
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
use App\Services\DomainAvailability;
use Illuminate\Support\Facades\Mail;

/*
 * Ordering hosting with "Register a new domain" or "Transfer" (GitHub issue
 * #48). The choice only wrote a line into the service notes: the name was
 * never checked, never priced, never put on the invoice and never sent to the
 * registrar - the customer paid for the hosting alone and believed the domain
 * was part of it. It now goes into the cart as a domain of its own, priced,
 * checked, and registered like any domain bought from the search page.
 */

function hostingDomainFixture(): array
{
    $currency = Currency::where('is_default', true)->first()
        ?? Currency::create(['code' => 'USD', 'prefix' => '$', 'suffix' => '', 'rate' => 1, 'is_default' => true]);

    $client = Client::factory()->create(['tax_exempt' => true]);
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->clients()->attach($client->id);

    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'type' => 'hosting',
        'show_domain_options' => true,
        'tax' => false,
    ]);
    Pricing::updateOrCreate(
        ['type' => 'product', 'rel_id' => $product->id, 'currency_id' => $currency->id],
        ['monthly' => 20]
    );

    DomainPricing::updateOrCreate(['extension' => '.com'], [
        'register_price' => 12, 'transfer_price' => 10, 'renew_price' => 14,
        'min_years' => 1, 'max_years' => 10, 'enabled' => true,
    ]);

    return compact('currency', 'client', 'user', 'product');
}

/** Stand-in for the registry: what it would say about any name. */
function registryAnswers(bool $checked, bool $available): void
{
    app()->instance(DomainAvailability::class, new class($checked, $available) extends DomainAvailability {
        public function __construct(private bool $isChecked, private bool $isAvailable) {}

        public function check(string $domain): array
        {
            return ['domain' => $domain, 'available' => $this->isAvailable, 'checked' => $this->isChecked];
        }
    });
}

function cartItemsFor(Client $client): array
{
    $cart = Cart::where('user_id', $client->id)->first();

    return $cart ? (json_decode((string) $cart->data, true)['items'] ?? []) : [];
}

function orderHosting(array $fx, array $input)
{
    return test()->actingAs($fx['user'])->post(route('client.cart.add'), $input + [
        'product_id' => $fx['product']->id,
        'billing_cycle' => 'monthly',
    ]);
}

it('puts a new domain in the cart, priced, and bills and registers it with the hosting', function () {
    Mail::fake();
    $fx = hostingDomainFixture();
    registryAnswers(checked: true, available: true);

    orderHosting($fx, ['domain' => 'Order-Me.com', 'domain_option' => 'register'])
        ->assertSessionHasNoErrors()->assertRedirect(route('client.cart.index'));

    $items = cartItemsFor($fx['client']);
    expect($items)->toHaveCount(2)
        ->and($items[0]['type'])->toBe('product')
        ->and($items[0]['domain'])->toBe('order-me.com')
        ->and($items[1])->toMatchArray(['type' => 'domain', 'domain' => 'order-me.com', 'action' => 'register', 'years' => 1])
        ->and((float) $items[1]['price'])->toBe(12.0);

    // The cart page shows it, with the hosting, in the total.
    test()->actingAs($fx['user'])->get(route('client.cart.index'))
        ->assertOk()->assertSee('order-me.com')->assertSee('Domain registration')->assertSee('1 year')->assertSee('32.00');

    $cart = app(CartService::class);
    $order = $cart->checkout($cart->getOrCreateCart($fx['client']->id), $fx['client']->id, 'banktransfer');

    $lines = InvoiceItem::where('invoice_id', $order->invoice_id)->get();
    expect($lines->where('type', 'Hosting')->count())->toBe(1)
        ->and((float) $lines->firstWhere('type', 'Domain')?->amount)->toBe(12.0)
        ->and((float) Invoice::findOrFail($order->invoice_id)->total)->toBe(32.0)
        // A domain record exists, so paying the invoice registers it.
        ->and(Domain::where('order_id', $order->id)->where('domain', 'order-me.com')->exists())->toBeTrue();
});

it('refuses a name that is already registered, and adds nothing', function () {
    $fx = hostingDomainFixture();
    registryAnswers(checked: true, available: false);

    orderHosting($fx, ['domain' => 'google.com', 'domain_option' => 'register'])
        ->assertSessionHasErrors('domain');

    expect(cartItemsFor($fx['client']))->toBe([]);
});

it('refuses a name it could not check, rather than guessing it is free', function () {
    $fx = hostingDomainFixture();
    registryAnswers(checked: false, available: false);

    orderHosting($fx, ['domain' => 'maybe-free.com', 'domain_option' => 'register'])
        ->assertSessionHasErrors('domain');

    expect(cartItemsFor($fx['client']))->toBe([]);
});

it('refuses an extension the shop does not sell, without leaving the hosting behind', function () {
    $fx = hostingDomainFixture();
    registryAnswers(checked: true, available: true);

    orderHosting($fx, ['domain' => 'nothanks.zzz', 'domain_option' => 'register'])
        ->assertSessionHasErrors('domain');

    expect(cartItemsFor($fx['client']))->toBe([]);
});

it('prices a transfer at the transfer rate and keeps the EPP code', function () {
    $fx = hostingDomainFixture();
    registryAnswers(checked: true, available: false);

    orderHosting($fx, ['domain' => 'mine.com', 'domain_option' => 'transfer', 'epp_code' => 'AbC-123'])
        ->assertSessionHasNoErrors();

    $items = cartItemsFor($fx['client']);
    expect($items[1])->toMatchArray(['type' => 'domain', 'action' => 'transfer', 'epp_code' => 'AbC-123'])
        ->and((float) $items[1]['price'])->toBe(10.0);
});

it('asks for the EPP code on a transfer', function () {
    $fx = hostingDomainFixture();
    registryAnswers(checked: true, available: false);

    orderHosting($fx, ['domain' => 'mine.com', 'domain_option' => 'transfer'])
        ->assertSessionHasErrors('epp_code');

    expect(cartItemsFor($fx['client']))->toBe([]);
});

it('refuses to transfer a name nobody has registered', function () {
    $fx = hostingDomainFixture();
    registryAnswers(checked: true, available: true);

    orderHosting($fx, ['domain' => 'nobodys.com', 'domain_option' => 'transfer', 'epp_code' => 'x'])
        ->assertSessionHasErrors('domain');

    expect(cartItemsFor($fx['client']))->toBe([]);
});

it('leaves a domain the customer already has alone', function () {
    $fx = hostingDomainFixture();
    registryAnswers(checked: true, available: false);

    orderHosting($fx, ['domain' => 'already-mine.com', 'domain_option' => 'own'])
        ->assertSessionHasNoErrors();

    $items = cartItemsFor($fx['client']);
    expect($items)->toHaveCount(1)->and($items[0]['type'])->toBe('product');
});

it('does not add the domain twice when it is already in the cart from the search page', function () {
    $fx = hostingDomainFixture();
    registryAnswers(checked: true, available: true);

    $cart = app(CartService::class);
    $cart->addDomain($cart->getOrCreateCart($fx['client']->id), 'twice.com', 'register', 1);

    orderHosting($fx, ['domain' => 'twice.com', 'domain_option' => 'register'])->assertSessionHasNoErrors();

    $domains = array_filter(cartItemsFor($fx['client']), fn ($i) => ($i['type'] ?? '') === 'domain');
    expect($domains)->toHaveCount(1);
});

it('quotes a name for the order summary', function () {
    $fx = hostingDomainFixture();

    registryAnswers(checked: true, available: true);
    test()->postJson(route('client.cart.domain-quote'), ['domain' => 'quote-me.com', 'type' => 'register'])
        ->assertOk()->assertJson(['status' => 'ok', 'domain' => 'quote-me.com', 'price' => 12, 'price_formatted' => '$12.00']);

    registryAnswers(checked: true, available: false);
    test()->postJson(route('client.cart.domain-quote'), ['domain' => 'google.com', 'type' => 'register'])
        ->assertOk()->assertJson(['status' => 'taken']);

    registryAnswers(checked: true, available: false);
    test()->postJson(route('client.cart.domain-quote'), ['domain' => 'mine.com', 'type' => 'transfer'])
        ->assertOk()->assertJson(['status' => 'ok', 'price' => 10]);
});

it('shows the domain in the order summary and asks for the EPP code on the configure page', function () {
    $fx = hostingDomainFixture();

    test()->actingAs($fx['user'])->get(route('client.store.configure', $fx['product']))
        ->assertOk()
        ->assertSee('id="summaryDomainRow"', false)
        ->assertSee('name="epp_code"', false)
        // Printed for the script with @json, which escapes the slashes.
        ->assertSee(json_encode(route('client.cart.domain-quote')), false);
});
