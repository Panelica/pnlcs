<?php

use App\Models\Client;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Testing\TestResponse;

/**
 * A webhook that marks an invoice paid without proving who sent it.
 *
 * The Authorize.net webhook checks its signature only when a signature header
 * is present: "if ($sigHeader && $payload && $txnKey)". Leave the header off
 * and the whole check is skipped, so anyone who can post to the endpoint can
 * name an invoice and have it marked paid for nothing. The other gateways
 * refuse an unsigned call.
 *
 * The comparison is also wrong for signatures that are genuine. The prefix is
 * removed with ltrim($sig, "sha512="), and ltrim takes a set of characters,
 * not a prefix - so a signature whose hex begins with s, h, a, 5, 1, 2 or =
 * has those digits eaten too and a correct signature is rejected.
 */
const ANET_KEY = 'test-transaction-key';

function payableInvoiceForWebhook(): Invoice
{
    $client = Client::factory()->create();

    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'status' => 'unpaid',
        'subtotal' => 40,
        'total' => 40,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'client_id' => $client->id,
        'type' => 'Hosting',
        'description' => 'Hosting',
        'amount' => 40,
        'taxed' => false,
    ]);

    GatewaySettings::updateOrCreate(
        ['gateway' => 'authorize', 'setting' => 'transaction_key'],
        ['value' => ANET_KEY]
    );

    return $invoice;
}

function anetPayload(Invoice $invoice, string $txnId): array
{
    return [
        'eventType' => 'net.authorize.payment.authcapture.created',
        'payload' => [
            'id' => $txnId,
            'merchantReferenceId' => 'INV-'.$invoice->id,
            'authAmount' => 40,
        ],
    ];
}

function anetPost(array $payload, ?string $signature): TestResponse
{
    $body = json_encode($payload);
    $headers = ['Content-Type' => 'application/json'];

    if ($signature !== null) {
        $headers['X-ANET-Signature'] = $signature;
    }

    return test()->call('POST', route('gateway.authorize.webhook'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_ANET_SIGNATURE' => $signature ?? '',
    ], $body);
}

it('does not pay an invoice for a webhook that carries no signature', function () {
    $invoice = payableInvoiceForWebhook();

    anetPost(anetPayload($invoice, 'FORGED-1'), null);

    expect(strtolower((string) $invoice->fresh()->status))->toBe('unpaid');
});

it('does not pay an invoice for a webhook signed with the wrong key', function () {
    $invoice = payableInvoiceForWebhook();

    $body = json_encode(anetPayload($invoice, 'FORGED-2'));
    anetPost(anetPayload($invoice, 'FORGED-2'), 'sha512='.hash_hmac('sha512', $body, 'not-the-key'));

    expect(strtolower((string) $invoice->fresh()->status))->toBe('unpaid');
});

it('accepts a genuine signature whose hex starts with a character of the prefix', function () {
    $invoice = payableInvoiceForWebhook();

    // Find a transaction id whose signature begins with one of the characters
    // in "sha512=", which is what the old prefix stripping ate.
    $payload = null;
    $signature = null;

    for ($i = 0; $i < 500; $i++) {
        $candidate = anetPayload($invoice, 'REAL-'.$i);
        $hash = hash_hmac('sha512', json_encode($candidate), ANET_KEY);

        if (in_array($hash[0], ['s', 'h', 'a', '5', '1', '2', '='], true)) {
            $payload = $candidate;
            $signature = 'sha512='.$hash;
            break;
        }
    }

    expect($signature)->not->toBeNull();

    anetPost($payload, $signature);

    expect(strtolower((string) $invoice->fresh()->status))->toBe('paid');
});

it('still ignores an event of another kind', function () {
    $invoice = payableInvoiceForWebhook();

    $payload = ['eventType' => 'net.authorize.customer.created', 'payload' => ['id' => 'X']];
    anetPost($payload, 'sha512='.hash_hmac('sha512', json_encode($payload), ANET_KEY));

    expect(strtolower((string) $invoice->fresh()->status))->toBe('unpaid');
});
