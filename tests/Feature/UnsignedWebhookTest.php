<?php

use App\Models\Client;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use App\Models\InvoiceItem;

/**
 * A webhook nobody signed, marking an invoice paid.
 *
 * The Stripe and Razorpay modules verified the signature only when a webhook
 * secret happened to be configured - and Stripe additionally only when both a
 * header and a raw body arrived. With any of those missing they fell through to
 * the ordinary processing, so an unsigned POST to the public webhook URL naming
 * an invoice id in its metadata marked that invoice paid. Payment then does
 * everything payment does: the order is accepted, the service is provisioned, a
 * suspended one comes back on.
 *
 * The Authorize.net module refuses in exactly this case, and says why in its
 * own comment. These two were left as they were.
 */
function webhookInvoice(): Invoice
{
    $client = Client::factory()->create();

    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'status' => 'unpaid',
        'subtotal' => 100,
        'total' => 100,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'client_id' => $client->id,
        'type' => 'Hosting',
        'description' => 'Hosting',
        'amount' => 100,
        'taxed' => false,
    ]);

    return $invoice;
}

function stripeWebhookBody(Invoice $invoice): string
{
    return json_encode([
        'type' => 'payment_intent.succeeded',
        'data' => ['object' => [
            'id' => 'pi_forged_1',
            'metadata' => ['invoice_id' => (string) $invoice->id],
            'amount_received' => 10000,
        ]],
    ]);
}

function razorpayWebhookBody(Invoice $invoice): string
{
    return json_encode([
        'event' => 'payment.captured',
        'payload' => ['payment' => ['entity' => [
            'id' => 'pay_forged_1',
            'notes' => ['invoice_id' => (string) $invoice->id],
            'amount' => 10000,
        ]]],
    ]);
}

it('does not pay an invoice on an unsigned stripe webhook', function () {
    $invoice = webhookInvoice();

    $this->call('POST', '/gateway/stripe/webhook', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], stripeWebhookBody($invoice));

    expect($invoice->fresh()->status)->toBe('unpaid');
});

it('does not pay an invoice on an unsigned razorpay webhook', function () {
    $invoice = webhookInvoice();

    $this->call('POST', '/gateway/razorpay/webhook', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], razorpayWebhookBody($invoice));

    expect($invoice->fresh()->status)->toBe('unpaid');
});

it('still pays an invoice on a stripe webhook that is signed', function () {
    GatewaySettings::updateOrCreate(
        ['gateway' => 'stripe', 'setting' => 'webhook_secret'],
        ['value' => 'whsec_test']
    );

    $invoice = webhookInvoice();
    $body = stripeWebhookBody($invoice);
    $timestamp = time();

    $this->call('POST', '/gateway/stripe/webhook', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_test'),
    ], $body);

    expect($invoice->fresh()->status)->toBe('paid');
});

it('still pays an invoice on a razorpay webhook that is signed', function () {
    GatewaySettings::updateOrCreate(
        ['gateway' => 'razorpay', 'setting' => 'webhook_secret'],
        ['value' => 'rzp_hook_secret']
    );

    $invoice = webhookInvoice();
    $body = razorpayWebhookBody($invoice);

    $this->call('POST', '/gateway/razorpay/webhook', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $body, 'rzp_hook_secret'),
    ], $body);

    expect($invoice->fresh()->status)->toBe('paid');
});

it('does not pay an invoice when the signature is wrong', function () {
    GatewaySettings::updateOrCreate(
        ['gateway' => 'razorpay', 'setting' => 'webhook_secret'],
        ['value' => 'rzp_hook_secret']
    );

    $invoice = webhookInvoice();

    $this->call('POST', '/gateway/razorpay/webhook', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_RAZORPAY_SIGNATURE' => 'not-the-signature',
    ], razorpayWebhookBody($invoice));

    expect($invoice->fresh()->status)->toBe('unpaid');
});
