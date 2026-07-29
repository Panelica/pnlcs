<?php

use App\Models\Affiliate;
use App\Models\Client;
use App\Models\Invoice;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Mail;

/**
 * One-time commission, and the customer whose id starts the same way.
 *
 * An affiliate paid once per referred customer was checked by searching the
 * commission descriptions for "client#1" — which also matches client#12,
 * client#19 and every other id that starts with a 1. Earning a commission
 * from one referred customer silently cancelled the commission owed for
 * another.
 */
function oneTimeAffiliate(): Affiliate
{
    return Affiliate::create([
        'client_id' => Client::factory()->create()->id,
        'visitors' => 0,
        'pay_type' => 'percentage',
        'pay_amount' => 10,
        'onetime' => true,
        'balance' => 0,
        'withdrawn' => 0,
    ]);
}

function payFor(Client $client, float $total, string $txn): void
{
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'status' => 'unpaid',
        'subtotal' => $total,
        'total' => $total,
    ]);

    app(PaymentService::class)->applyPayment($invoice, 'stripe', $txn, $total);
}

test('a commission earned from one customer does not cancel another customers', function () {
    Mail::fake();

    $affiliate = oneTimeAffiliate();

    // Two customers whose ids share a prefix: 800001 and 8000012.
    $shortId = Client::factory()->create(['id' => 800001, 'affiliate_id' => $affiliate->id]);
    $longId = Client::factory()->create(['id' => 8000012, 'affiliate_id' => $affiliate->id]);

    payFor($longId, 100, 'TXN-LONG');

    expect($affiliate->fresh()->balance)->toEqual(10.0);

    // A different customer, whose id merely starts with the same digits.
    payFor($shortId, 200, 'TXN-SHORT');

    expect($affiliate->fresh()->balance)->toEqual(30.0);
});

test('a one-time affiliate is still paid only once for the same customer', function () {
    Mail::fake();

    $affiliate = oneTimeAffiliate();
    $client = Client::factory()->create(['affiliate_id' => $affiliate->id]);

    payFor($client, 100, 'TXN-FIRST');
    payFor($client, 100, 'TXN-SECOND');

    expect($affiliate->fresh()->balance)->toEqual(10.0);
});
