<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;

/**
 * Who may start a payment against an invoice.
 *
 * The six browser-facing capture endpoints sat in a route group commented
 * "authenticated, CSRF-protected" that carried no auth middleware, and not one
 * of them checked whose invoice it was. Invoice ids are sequential, so anyone
 * could POST /gateway/stripe/intent/{id} for somebody else's invoice and be
 * handed a live Stripe client_secret for it — a payment secret created in the
 * merchant's account, for another customer's bill.
 */
function someoneElsesInvoice(): Invoice
{
    return Invoice::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'status' => 'unpaid',
        'total' => 250,
    ]);
}

function signedInCustomer(): array
{
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);

    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'status' => 'unpaid',
        'total' => 40,
    ]);

    return [$user, $invoice];
}

$endpoints = [
    'gateway.stripe.intent',
    'gateway.stripe.confirm',
    'gateway.paypal.capture',
    'gateway.authorize.capture',
    'gateway.mollie.capture',
    'gateway.razorpay.capture',
];

test('a stranger cannot start a payment on an invoice', function () use ($endpoints) {
    $invoice = someoneElsesInvoice();

    foreach ($endpoints as $route) {
        $response = $this->post(route($route, $invoice));

        expect($response->status())->toBeIn([302, 401], "endpoint {$route}");
    }
});

test('a customer cannot start a payment on somebody elses invoice', function () use ($endpoints) {
    [$user] = signedInCustomer();
    $invoice = someoneElsesInvoice();

    foreach ($endpoints as $route) {
        $this->actingAs($user)->post(route($route, $invoice))->assertForbidden();
    }
});

test('a customer can still start a payment on their own invoice', function () {
    [$user, $invoice] = signedInCustomer();

    // Stripe is not configured here, so the module refuses — but it must be the
    // module refusing, not the ownership check.
    $this->actingAs($user)->post(route('gateway.stripe.intent', $invoice))
        ->assertOk()
        ->assertJson(['success' => false]);
});

test('a stranger is not told whether an invoice exists', function () {
    $invoice = someoneElsesInvoice();

    $response = $this->post(route('gateway.stripe.intent', $invoice));

    expect($response->getContent())->not->toContain('client_secret')
        ->and($response->getContent())->not->toContain('250');
});
