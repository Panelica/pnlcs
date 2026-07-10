<?php

use App\Models\Client;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Transaction;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Http;
use Modules\Gateways\PayPal\PayPalModule;

beforeEach(function () {
    foreach (['client_id' => 'cid', 'client_secret' => 'csec', 'sandbox' => '1'] as $k => $v) {
        GatewaySettings::create(['gateway' => 'paypal', 'setting' => $k, 'value' => $v]);
    }
});

function fakePayPalToken(): void
{
    Http::fake([
        '*/v1/oauth2/token' => Http::response(['access_token' => 'tok-123'], 200),
    ]);
}

// ---------------------------------------------------------------------------
// verifyCapture
// ---------------------------------------------------------------------------

test('verifyCapture accepts a COMPLETED capture and returns the real amount', function () {
    Http::fake([
        '*/v1/oauth2/token'         => Http::response(['access_token' => 'tok'], 200),
        '*/v2/payments/captures/*'  => Http::response(['status' => 'COMPLETED', 'amount' => ['value' => '42.50', 'currency_code' => 'USD']], 200),
    ]);

    $result = (new PayPalModule())->verifyCapture('CAP-REAL');

    expect($result['success'])->toBeTrue()
        ->and($result['amount'])->toBe(42.5)
        ->and($result['currency'])->toBe('USD');
});

test('verifyCapture rejects a non-COMPLETED capture', function () {
    Http::fake([
        '*/v1/oauth2/token'        => Http::response(['access_token' => 'tok'], 200),
        '*/v2/payments/captures/*' => Http::response(['status' => 'PENDING', 'amount' => ['value' => '10.00']], 200),
    ]);

    $result = (new PayPalModule())->verifyCapture('CAP-PENDING');

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('PENDING');
});

test('verifyCapture rejects when PayPal does not know the capture', function () {
    Http::fake([
        '*/v1/oauth2/token'        => Http::response(['access_token' => 'tok'], 200),
        '*/v2/payments/captures/*' => Http::response(['name' => 'RESOURCE_NOT_FOUND'], 404),
    ]);

    $result = (new PayPalModule())->verifyCapture('CAP-FAKE');

    expect($result['success'])->toBeFalse();
});

// ---------------------------------------------------------------------------
// processWebhook — the core security fix
// ---------------------------------------------------------------------------

test('a forged PAYMENT.CAPTURE.COMPLETED webhook is rejected when PayPal cannot verify it', function () {
    Http::fake([
        '*/v1/oauth2/token'        => Http::response(['access_token' => 'tok'], 200),
        '*/v2/payments/captures/*' => Http::response(['name' => 'RESOURCE_NOT_FOUND'], 404),
    ]);

    $result = (new PayPalModule())->processWebhook([
        'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
        'resource'   => ['id' => 'FORGED-CAP', 'purchase_units' => [['reference_id' => 'INV-999']]],
    ]);

    expect($result['success'])->toBeFalse();
});

test('a genuine webhook passes verification and returns the verified amount', function () {
    Http::fake([
        '*/v1/oauth2/token'        => Http::response(['access_token' => 'tok'], 200),
        '*/v2/payments/captures/*' => Http::response(['status' => 'COMPLETED', 'amount' => ['value' => '75.00', 'currency_code' => 'USD']], 200),
    ]);

    $result = (new PayPalModule())->processWebhook([
        'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
        'resource'   => ['id' => 'REAL-CAP', 'purchase_units' => [['reference_id' => 'INV-123']]],
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['transaction_id'])->toBe('REAL-CAP')
        ->and($result['invoice_id'])->toBe('123')
        ->and($result['amount'])->toBe(75.0);
});

// ---------------------------------------------------------------------------
// Controller capture endpoint — the other trust hole
// ---------------------------------------------------------------------------

test('the paypal capture endpoint refuses a forged capture id and leaves the invoice unpaid', function () {
    Http::fake([
        '*/v1/oauth2/token'        => Http::response(['access_token' => 'tok'], 200),
        '*/v2/payments/captures/*' => Http::response(['name' => 'RESOURCE_NOT_FOUND'], 404),
    ]);

    $client  = Client::factory()->create();
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => 500.0]);

    $response = $this->postJson("/gateway/paypal/capture/{$invoice->id}", ['capture_id' => 'ATTACKER-CAP']);

    $response->assertOk();
    expect($response->json('success'))->toBeFalse()
        ->and($invoice->fresh()->status)->toBe('unpaid')
        ->and(Transaction::where('invoice_id', $invoice->id)->count())->toBe(0);
});

test('the paypal capture endpoint credits only the verified amount, not the client total', function () {
    Http::fake([
        '*/v1/oauth2/token'        => Http::response(['access_token' => 'tok'], 200),
        '*/v2/payments/captures/*' => Http::response(['status' => 'COMPLETED', 'amount' => ['value' => '500.00', 'currency_code' => 'USD']], 200),
    ]);

    $client  = Client::factory()->create();
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => 500.0]);

    $response = $this->postJson("/gateway/paypal/capture/{$invoice->id}", ['capture_id' => 'GENUINE-CAP']);

    $response->assertOk();
    expect($response->json('success'))->toBeTrue()
        ->and($invoice->fresh()->status)->toBe('paid')
        ->and((float) Transaction::where('invoice_id', $invoice->id)->first()->amount_in)->toBe(500.0);
});
