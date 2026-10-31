<?php

use App\Models\Client;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Modules\Gateways\PayPal\PayPalModule;

/*
 * The PayPal buttons build the order in the browser, currency and all, and the
 * capture is then verified with PayPal before it is credited. The verification
 * read the amount but not the currency, so a capture of 100 in a currency worth
 * far less than the shop's was credited as 100 in the shop's. A capture is now
 * credited only in the currency the shop charges in.
 */

beforeEach(function () {
    foreach (['client_id' => 'cid', 'client_secret' => 'csec', 'sandbox' => '1'] as $k => $v) {
        GatewaySettings::updateOrCreate(['gateway' => 'paypal', 'setting' => $k], ['value' => $v]);
    }
});

function fakeCapture(string $value, string $currency): void
{
    Http::fake([
        '*/v1/oauth2/token' => Http::response(['access_token' => 'tok'], 200),
        '*/v2/payments/captures/*' => Http::response(['status' => 'COMPLETED', 'amount' => ['value' => $value, 'currency_code' => $currency], 'supplementary_data' => ['related_ids' => ['order_id' => 'ORDER-1']]], 200),
        // The order the capture was taken on names the invoice being paid.
        '*/v2/checkout/orders/*' => fn () => Http::response(['purchase_units' => [['reference_id' => 'INV-'.Invoice::max('id')]]], 200),
    ]);
}

function captureInvoice(): array
{
    $client = Client::factory()->create();
    $user = User::factory()->create();
    $user->clients()->attach($client->id);

    return [$user, Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => 100.0])];
}

test('a capture in another currency is refused by verification', function () {
    fakeCapture('100.00', 'HUF');

    $result = (new PayPalModule)->verifyCapture('CAP-HUF');

    expect($result['success'])->toBeFalse()->and($result['message'])->toContain('HUF');
});

test('a capture in the shop currency is still accepted, whatever case it is written in', function () {
    fakeCapture('100.00', strtolower(shop_currency_code()));

    expect((new PayPalModule)->verifyCapture('CAP-OK')['success'])->toBeTrue();
});

test('the capture endpoint does not mark an invoice paid with another currency', function () {
    fakeCapture('100.00', 'HUF');
    [$user, $invoice] = captureInvoice();

    $response = $this->actingAs($user)->postJson("/gateway/paypal/capture/{$invoice->id}", ['capture_id' => 'CAP-HUF']);

    expect($response->json('success'))->toBeFalse()
        ->and($invoice->fresh()->status)->toBe('unpaid')
        ->and(Transaction::where('invoice_id', $invoice->id)->count())->toBe(0);
});

test('a webhook for a capture in another currency is not applied', function () {
    fakeCapture('100.00', 'HUF');
    [, $invoice] = captureInvoice();

    $result = (new PayPalModule)->processWebhook([
        'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
        'resource' => ['id' => 'CAP-HUF', 'purchase_units' => [['reference_id' => 'INV-'.$invoice->id]]],
    ]);

    expect($result['success'])->toBeFalse()
        ->and(Transaction::where('invoice_id', $invoice->id)->count())->toBe(0);
});
