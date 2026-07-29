<?php

use App\Models\Cart;
use App\Models\Client;
use App\Models\Domain;
use App\Models\DomainPricing;
use App\Models\Invoice;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Paying for the years you get.
 *
 * A domain was priced at one year whatever term the customer chose. The order
 * registered it for the full term, the invoice line even said "5 Year(s)", and
 * the customer was charged for one. The renewal price was wrong too: the
 * domain kept the registration price as its recurring amount, so every renewal
 * afterwards was billed at the cheaper introductory rate.
 */
function tldPricing(): DomainPricing
{
    return DomainPricing::updateOrCreate(
        ['extension' => '.com'],
        [
            'register_price' => 10.99,
            'renew_price' => 12.99,
            'transfer_price' => 9.99,
            'min_years' => 1,
            'max_years' => 5,
            'enabled' => true,
            'sort_order' => 0,
        ]
    );
}

function cartFor(Client $client): Cart
{
    return app(CartService::class)->getOrCreateCart($client->id);
}

test('a three year registration costs three years', function () {
    tldPricing();
    $client = Client::factory()->create();

    $cart = app(CartService::class)->addDomain(cartFor($client), 'example.com', 'register', 3);

    $item = json_decode($cart->fresh()->data, true)['items'][0];

    expect((float) $item['price'])->toEqual(32.97);
});

test('one year is unchanged', function () {
    tldPricing();
    $client = Client::factory()->create();

    $cart = app(CartService::class)->addDomain(cartFor($client), 'example.com', 'register', 1);

    expect((float) json_decode($cart->fresh()->data, true)['items'][0]['price'])->toEqual(10.99);
});

test('a transfer is priced the same way', function () {
    tldPricing();
    $client = Client::factory()->create();

    $cart = app(CartService::class)->addDomain(cartFor($client), 'example.com', 'transfer', 2);

    expect((float) json_decode($cart->fresh()->data, true)['items'][0]['price'])->toEqual(19.98);
});

test('a term the tld does not offer is refused', function () {
    tldPricing();
    $client = Client::factory()->create();

    app(CartService::class)->addDomain(cartFor($client), 'example.com', 'register', 9);
})->throws(ValidationException::class);

test('the invoice charges the term the domain is registered for', function () {
    Mail::fake();
    tldPricing();

    $user = User::factory()->create();
    $client = Client::factory()->create(['tax_exempt' => true]);
    $user->clients()->attach($client->id);

    $cart = app(CartService::class)->addDomain(cartFor($client), 'termcheck.com', 'register', 3);
    app(CartService::class)->checkout($cart, $client->id, 'banktransfer');

    $invoice = Invoice::latest('id')->firstOrFail();
    $domain = Domain::where('domain', 'termcheck.com')->firstOrFail();

    expect((float) $invoice->total)->toEqual(32.97)
        ->and((float) $domain->first_payment_amount)->toEqual(32.97);
});

test('the renewal is priced at the renewal rate for the same term', function () {
    Mail::fake();
    tldPricing();

    $user = User::factory()->create();
    $client = Client::factory()->create(['tax_exempt' => true]);
    $user->clients()->attach($client->id);

    $cart = app(CartService::class)->addDomain(cartFor($client), 'renewcheck.com', 'register', 3);
    app(CartService::class)->checkout($cart, $client->id, 'banktransfer');

    $domain = Domain::where('domain', 'renewcheck.com')->firstOrFail();

    expect((float) $domain->recurring_amount)->toEqual(38.97);
});
