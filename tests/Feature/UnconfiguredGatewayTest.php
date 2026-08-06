<?php

use App\Models\Client;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;

/**
 * A payment method that cannot take a payment.
 *
 * Both places that offer payment methods - the checkout and the invoice page -
 * ask only whether a gateway is ticked active. Ticking it is one setting; the
 * keys it needs to authenticate are others, and nothing checks those.
 *
 * So an operator who enables Stripe before pasting the secret key offers it to
 * customers. The customer picks it, and the payment fails at the last step,
 * after the order has been placed - which is the most expensive moment for it
 * to fail.
 */
function activeGateway(string $gateway, array $settings = []): void
{
    GatewaySettings::updateOrCreate(
        ['gateway' => $gateway, 'setting' => 'active'],
        ['value' => '1']
    );

    foreach ($settings as $name => $value) {
        GatewaySettings::updateOrCreate(
            ['gateway' => $gateway, 'setting' => $name],
            ['value' => $value]
        );
    }
}

function payableInvoiceFor(User $user, Client $client): Invoice
{
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'status' => 'unpaid',
        'subtotal' => 50,
        'total' => 50,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'client_id' => $client->id,
        'type' => 'Hosting',
        'description' => 'Hosting',
        'amount' => 50,
        'taxed' => false,
    ]);

    return $invoice;
}

function payingCustomer(): array
{
    $client = Client::factory()->create();
    $user = User::factory()->create();
    $user->clients()->attach($client->id);

    return [$user, $client];
}

it('does not offer a gateway whose keys are missing', function () {
    activeGateway('stripe');

    [$user, $client] = payingCustomer();
    $invoice = payableInvoiceFor($user, $client);

    $html = $this->actingAs($user)
        ->get(route('client.invoices.show', $invoice))
        ->assertOk()
        ->getContent();

    // The page carries a static helper script that mentions the gateway by
    // name; what matters is whether it is offered as a choice.
    expect($html)->not->toContain("switchGw(event, 'stripe')");
});

it('offers a gateway once its keys are there', function () {
    activeGateway('stripe', [
        'secret_key' => 'sk_test_123',
        'publishable_key' => 'pk_test_123',
    ]);

    [$user, $client] = payingCustomer();
    $invoice = payableInvoiceFor($user, $client);

    $html = $this->actingAs($user)
        ->get(route('client.invoices.show', $invoice))
        ->assertOk()
        ->getContent();

    expect($html)->toContain("switchGw(event, 'stripe')");
});

it('still offers bank transfer, which needs nothing', function () {
    activeGateway('banktransfer');

    [$user, $client] = payingCustomer();
    $invoice = payableInvoiceFor($user, $client);

    $this->actingAs($user)
        ->get(route('client.invoices.show', $invoice))
        ->assertOk()
        ->assertSee('ank', false);
});
