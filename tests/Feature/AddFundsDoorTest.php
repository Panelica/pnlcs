<?php

use App\Models\Client;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use App\Models\User;
use App\Services\InvoiceService;

/**
 * The add funds page went its own way.
 *
 * Everywhere else asks the registry which gateways can actually take a
 * payment. This page lists every gateway that has ever had a setting saved -
 * switched off, half configured, it makes no difference - and its own handler
 * accepts anything with a row in that table. When there are no rows at all it
 * falls back to offering PayPal, which is the one case where nothing is
 * configured for certain.
 *
 * It also mints its own invoice number, eight random characters, instead of
 * asking for the next one in the series. That number then poisons the series:
 * the generator takes the newest INV- row, reads the characters after the
 * prefix as a number, gets zero, and starts again from one. This installation
 * has eight invoice numbers issued three times over to different customers.
 */
function fundsCustomer(): User
{
    $client = Client::factory()->create();
    $user = User::factory()->create();
    $user->clients()->attach($client->id);

    return $user;
}

function gatewayRow(string $gateway, array $settings): void
{
    foreach ($settings as $name => $value) {
        GatewaySettings::updateOrCreate(
            ['gateway' => $gateway, 'setting' => $name],
            ['value' => $value]
        );
    }
}

it('does not offer a gateway nobody switched on', function () {
    gatewayRow('stripe', ['active' => '0', 'secret_key' => 'sk_test', 'publishable_key' => 'pk_test']);
    gatewayRow('banktransfer', ['active' => '1']);

    $html = $this->actingAs(fundsCustomer())
        ->get(route('client.funds.index'))
        ->assertOk()
        ->getContent();

    expect($html)->not->toContain('value="stripe"');
    expect($html)->toContain('value="banktransfer"');
});

it('does not offer a switched-on gateway whose keys are missing', function () {
    gatewayRow('stripe', ['active' => '1']);

    $html = $this->actingAs(fundsCustomer())
        ->get(route('client.funds.index'))
        ->assertOk()
        ->getContent();

    expect($html)->not->toContain('value="stripe"');
});

it('offers nothing rather than PayPal when nothing is configured', function () {
    GatewaySettings::query()->delete();

    $html = $this->actingAs(fundsCustomer())
        ->get(route('client.funds.index'))
        ->assertOk()
        ->getContent();

    expect($html)->not->toContain('value="paypal"');
});

it('refuses to take funds through a gateway that cannot take them', function () {
    gatewayRow('stripe', ['active' => '0', 'secret_key' => 'sk_test', 'publishable_key' => 'pk_test']);

    $before = Invoice::count();

    $this->actingAs(fundsCustomer())
        ->post(route('client.funds.store'), ['amount' => 25, 'payment_method' => 'stripe'])
        ->assertRedirect();

    expect(Invoice::count())->toBe($before);
});

it('numbers the add funds invoice from the same series as every other', function () {
    gatewayRow('banktransfer', ['active' => '1']);

    $client = Client::factory()->create();
    Invoice::factory()->create(['client_id' => $client->id, 'invoice_num' => 'INV-000041']);

    $this->actingAs(fundsCustomer())
        ->post(route('client.funds.store'), ['amount' => 25, 'payment_method' => 'banktransfer']);

    expect(Invoice::latest('id')->value('invoice_num'))->toBe('INV-000042');
});

it('does not hand out a number that is already in use', function () {
    $client = Client::factory()->create();
    Invoice::factory()->create(['client_id' => $client->id, 'invoice_num' => 'INV-000005']);
    // Newest by id, and not a number at all: the generator used to read this
    // as zero and start the series again.
    Invoice::factory()->create(['client_id' => $client->id, 'invoice_num' => 'INV-ZZZZZZZZ']);

    $next = app(InvoiceService::class)->generateInvoiceNumber();

    expect($next)->toBe('INV-000006');
    expect(Invoice::where('invoice_num', $next)->exists())->toBeFalse();
});
