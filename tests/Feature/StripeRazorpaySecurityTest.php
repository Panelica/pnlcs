<?php

use App\Models\Client;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use App\Models\Transaction;
use Illuminate\Support\Facades\Http;

/**
 * Payment-forgery guards for the Stripe and Razorpay client-confirm endpoints.
 *
 * Both endpoints previously credited an invoice purely on browser-supplied ids
 * (Stripe payment_intent_id / Razorpay payment_id) with no server-side check,
 * so anyone could POST an arbitrary id and get free services. The fix verifies
 * the payment with the gateway, binds it to THIS invoice, and credits only the
 * gateway-reported amount.
 */

beforeEach(function () {
    GatewaySettings::create(['gateway' => 'stripe', 'setting' => 'secret_key', 'value' => 'sk_test_x']);
    GatewaySettings::create(['gateway' => 'stripe', 'setting' => 'publishable_key', 'value' => 'pk_test_x']);
    foreach (['key_id' => 'rzp_key', 'key_secret' => 'rzp_secret'] as $k => $v) {
        GatewaySettings::create(['gateway' => 'razorpay', 'setting' => $k, 'value' => $v]);
    }
});

// ===================== Stripe =====================

test('stripe confirm refuses a forged payment_intent_id and leaves the invoice unpaid', function () {
    Http::fake([
        '*/v1/payment_intents/*' => Http::response(['id' => 'pi_x', 'status' => 'requires_payment_method'], 200),
    ]);

    $client  = Client::factory()->create();
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => 500.0]);

    $response = $this->postJson("/gateway/stripe/confirm/{$invoice->id}", ['payment_intent_id' => 'pi_ATTACKER']);

    $response->assertOk();
    expect($response->json('success'))->toBeFalse()
        ->and($invoice->fresh()->status)->toBe('unpaid')
        ->and(Transaction::where('invoice_id', $invoice->id)->count())->toBe(0);
});

test('stripe confirm rejects an intent that belongs to a different invoice', function () {
    $client  = Client::factory()->create();
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => 500.0]);

    Http::fake([
        '*/v1/payment_intents/*' => Http::response([
            'id' => 'pi_ok', 'status' => 'succeeded',
            'amount_received' => 50000,
            'metadata' => ['invoice_id' => $invoice->id + 9999], // foreign invoice
        ], 200),
    ]);

    $response = $this->postJson("/gateway/stripe/confirm/{$invoice->id}", ['payment_intent_id' => 'pi_ok']);

    expect($response->json('success'))->toBeFalse()
        ->and($invoice->fresh()->status)->toBe('unpaid')
        ->and(Transaction::where('invoice_id', $invoice->id)->count())->toBe(0);
});

test('stripe confirm credits only the gateway-reported amount for a genuine intent', function () {
    $client  = Client::factory()->create();
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => 500.0]);

    Http::fake([
        '*/v1/payment_intents/*' => Http::response([
            'id' => 'pi_real', 'status' => 'succeeded',
            'amount_received' => 50000,
            'metadata' => ['invoice_id' => $invoice->id],
        ], 200),
    ]);

    $response = $this->postJson("/gateway/stripe/confirm/{$invoice->id}", ['payment_intent_id' => 'pi_real']);

    $response->assertOk();
    expect($response->json('success'))->toBeTrue()
        ->and($invoice->fresh()->status)->toBe('paid')
        ->and((float) Transaction::where('invoice_id', $invoice->id)->first()->amount_in)->toBe(500.0);
});

// ===================== Razorpay =====================

function rzpSign(string $orderId, string $paymentId, string $secret): string
{
    return hash_hmac('sha256', "{$orderId}|{$paymentId}", $secret);
}

test('razorpay confirm refuses an invalid signature and leaves the invoice unpaid', function () {
    Http::fake(['*/v1/orders/*' => Http::response(['status' => 'paid', 'amount_paid' => 50000, 'notes' => ['invoice_id' => 1]], 200)]);

    $client  = Client::factory()->create();
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => 500.0]);

    $response = $this->postJson("/gateway/razorpay/capture/{$invoice->id}", [
        'confirm' => true,
        'razorpay_order_id' => 'order_x',
        'razorpay_payment_id' => 'pay_x',
        'razorpay_signature' => 'deadbeef', // wrong
    ]);

    $response->assertOk();
    expect($response->json('success'))->toBeFalse()
        ->and($invoice->fresh()->status)->toBe('unpaid')
        ->and(Transaction::where('invoice_id', $invoice->id)->count())->toBe(0);
});

test('razorpay confirm rejects a valid signature whose order is for a different invoice', function () {
    $client  = Client::factory()->create();
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => 500.0]);

    Http::fake(['*/v1/orders/*' => Http::response([
        'status' => 'paid', 'amount_paid' => 50000,
        'notes' => ['invoice_id' => $invoice->id + 9999],
    ], 200)]);

    $sig = rzpSign('order_x', 'pay_x', 'rzp_secret');
    $response = $this->postJson("/gateway/razorpay/capture/{$invoice->id}", [
        'confirm' => true,
        'razorpay_order_id' => 'order_x',
        'razorpay_payment_id' => 'pay_x',
        'razorpay_signature' => $sig,
    ]);

    expect($response->json('success'))->toBeFalse()
        ->and($invoice->fresh()->status)->toBe('unpaid')
        ->and(Transaction::where('invoice_id', $invoice->id)->count())->toBe(0);
});

test('razorpay confirm credits the gateway amount for a valid signature and matching order', function () {
    $client  = Client::factory()->create();
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => 500.0]);

    Http::fake(['*/v1/orders/*' => Http::response([
        'status' => 'paid', 'amount_paid' => 50000,
        'notes' => ['invoice_id' => $invoice->id],
    ], 200)]);

    $sig = rzpSign('order_ok', 'pay_ok', 'rzp_secret');
    $response = $this->postJson("/gateway/razorpay/capture/{$invoice->id}", [
        'confirm' => true,
        'razorpay_order_id' => 'order_ok',
        'razorpay_payment_id' => 'pay_ok',
        'razorpay_signature' => $sig,
    ]);

    $response->assertOk();
    expect($response->json('success'))->toBeTrue()
        ->and($invoice->fresh()->status)->toBe('paid')
        ->and((float) Transaction::where('invoice_id', $invoice->id)->first()->amount_in)->toBe(500.0);
});
