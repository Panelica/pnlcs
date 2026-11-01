<?php

use App\Models\Client;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Modules\Gateways\PayPal\PayPalModule;

/*
 * A PayPal capture pays the invoice it was taken for, and no other. The order
 * is opened with the invoice in its reference_id; the browser and the webhook
 * name a capture, and the invoice it pays is read back from PayPal.
 */

beforeEach(function () {
    foreach (['client_id' => 'cid', 'client_secret' => 'csec', 'sandbox' => '1'] as $k => $v) {
        GatewaySettings::create(['gateway' => 'paypal', 'setting' => $k, 'value' => $v]);
    }
});

/** PayPal knows one capture of $value, taken on an order for invoice $paidFor (null: the order names none). */
function ppfiPayPal(?int $paidFor, string $value = '50.00'): void
{
    Http::fake([
        '*/v1/oauth2/token' => Http::response(['access_token' => 'tok'], 200),
        '*/v2/payments/captures/*' => Http::response([
            'id' => 'CAP-1',
            'status' => 'COMPLETED',
            'amount' => ['value' => $value, 'currency_code' => shop_currency_code()],
            'supplementary_data' => ['related_ids' => ['order_id' => 'ORDER-1']],
        ], 200),
        '*/v2/checkout/orders/*' => Http::response(['purchase_units' => [['reference_id' => $paidFor ? 'INV-'.$paidFor : 'SOMETHING-ELSE']]], 200),
    ]);
}

/** Two unpaid invoices of one customer, and a login on that account. */
function ppfiAccount(): array
{
    $client = Client::factory()->create();
    $user = User::factory()->create();
    $user->clients()->attach($client->id);

    return [
        $user,
        Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => 50.0]),
        Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => 50.0]),
    ];
}

test('a capture taken for one invoice does not pay another one of the same customer', function () {
    [$user, $paid, $other] = ppfiAccount();
    ppfiPayPal($paid->id);

    $this->actingAs($user)->postJson("/gateway/paypal/capture/{$other->id}", ['capture_id' => 'CAP-1'])
        ->assertJson(['success' => false]);

    expect($other->fresh()->status)->toBe('unpaid')
        ->and(Transaction::where('invoice_id', $other->id)->count())->toBe(0);
});

test('a capture on an order that names no invoice pays nothing', function () {
    [$user, $invoice] = ppfiAccount();
    ppfiPayPal(null);

    $this->actingAs($user)->postJson("/gateway/paypal/capture/{$invoice->id}", ['capture_id' => 'CAP-1'])
        ->assertJson(['success' => false]);

    expect($invoice->fresh()->status)->toBe('unpaid');
});

test('the capture still pays the invoice it was taken for, once', function () {
    [$user, $invoice, $other] = ppfiAccount();
    ppfiPayPal($invoice->id);

    $this->actingAs($user)->postJson("/gateway/paypal/capture/{$invoice->id}", ['capture_id' => 'CAP-1'])
        ->assertJson(['success' => true]);
    $this->actingAs($user)->postJson("/gateway/paypal/capture/{$invoice->id}", ['capture_id' => 'CAP-1']);

    expect($invoice->fresh()->status)->toBe('paid')
        ->and(Transaction::where('transaction_id', 'CAP-1')->count())->toBe(1)
        ->and($other->fresh()->status)->toBe('unpaid');
});

test('the webhook pays the invoice PayPal names, whatever the body says', function () {
    [, $paid, $other] = ppfiAccount();
    ppfiPayPal($paid->id);

    $result = (new PayPalModule)->processWebhook([
        'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
        'resource' => ['id' => 'CAP-1', 'purchase_units' => [['reference_id' => 'INV-'.$other->id]]],
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['invoice_id'])->toBe((string) $paid->id);
});

test('the webhook pays nothing for a capture whose order names no invoice here', function () {
    ppfiAccount();
    ppfiPayPal(999999);

    $result = (new PayPalModule)->processWebhook([
        'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
        'resource' => ['id' => 'CAP-1'],
    ]);

    expect($result['success'])->toBeFalse();
});

test('the same capture spelled differently is still one payment', function () {
    [$user, $invoice] = ppfiAccount();
    ppfiPayPal($invoice->id, '20.00');

    foreach (['CAP-1', 'CAP-1#2', 'CAP-1 '] as $spelling) {
        $this->actingAs($user)->postJson("/gateway/paypal/capture/{$invoice->id}", ['capture_id' => $spelling]);
    }

    expect(Transaction::where('invoice_id', $invoice->id)->count())->toBe(1)
        ->and(Transaction::where('invoice_id', $invoice->id)->value('transaction_id'))->toBe('CAP-1')
        ->and($invoice->fresh()->status)->toBe('partially_paid');
});
