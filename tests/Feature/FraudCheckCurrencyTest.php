<?php

use App\Models\Client;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Setting;
use App\Services\Fraud\FraudLabsProClient;
use App\Services\Fraud\MaxMindMinFraudClient;
use Illuminate\Support\Facades\Http;

/**
 * Orders have no currency column, so `$order->currency ?? 'USD'` always sent
 * USD: a EUR 49.90 order was screened as USD 49.90.
 */
function euroOrder(): Order
{
    Currency::query()->update(['is_default' => false]);
    Currency::updateOrCreate(['code' => 'EUR'], ['name' => 'EUR', 'prefix' => '€', 'rate' => 1.0, 'is_default' => true]);
    $client = Client::factory()->create(['email' => 'screened@example.com', 'country' => 'DE']);
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'subtotal' => 49.90, 'total' => 49.90]);

    return Order::create([
        'order_num' => 'FR-'.uniqid(), 'client_id' => $client->id, 'date' => now(),
        'amount' => 49.90, 'status' => 'pending', 'ip_address' => '203.0.113.7', 'invoice_id' => $invoice->id,
    ]);
}

it('tells minFraud the shop currency', function () {
    Http::fake([MaxMindMinFraudClient::ENDPOINT => Http::response(['risk_score' => 0.5], 200)]);
    Setting::set('MaxMindEnabled', '1');
    Setting::set('MaxMindAccountId', '123456');
    Setting::set('MaxMindLicenseKey', 'test_license_key');

    app(MaxMindMinFraudClient::class)->score(euroOrder());

    Http::assertSent(fn ($request) => $request['order']['currency'] === 'EUR');
});

it('tells FraudLabs the shop currency', function () {
    Http::fake([FraudLabsProClient::ENDPOINT => Http::response(['fraudlabspro_score' => 10, 'fraudlabspro_status' => 'APPROVE'], 200)]);
    Setting::set('FraudLabsEnabled', '1');
    Setting::set('FraudLabsApiKey', 'test_fraudlabs_key');

    app(FraudLabsProClient::class)->score(euroOrder());

    Http::assertSent(fn ($request) => $request['currency'] === 'EUR');
});

it('falls back to the shop currency for an order without an invoice', function () {
    $order = euroOrder();
    $order->update(['invoice_id' => null]);

    expect($order->fresh()->currencyCode())->toBe('EUR');
});
