<?php

use App\Http\Middleware\AffiliateTracking;
use App\Models\Affiliate;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Services\AffiliateService;
use App\Services\PaymentService;

/**
 * The affiliate programme was dead end to end: clients.affiliate_id never
 * existed as a column (writes threw "Unknown column"), nothing ever read the
 * pnlcs_aff cookie that the tracking middleware set, and commission processing
 * therefore always returned early. Live proof at the time: 15 affiliates,
 * 0 visitors, 0.00 total balance, 0 history rows.
 */
function affiliateFor(): Affiliate
{
    return Affiliate::create([
        'client_id' => Client::factory()->create()->id,
        'visitors' => 0, 'pay_type' => 'percentage', 'pay_amount' => 10.00,
        'onetime' => false, 'balance' => 0, 'withdrawn' => 0,
    ]);
}

test('clients can store an affiliate reference', function () {
    $affiliate = affiliateFor();
    $client = Client::factory()->create();

    $client->update(['affiliate_id' => $affiliate->id]);

    expect($client->fresh()->affiliate_id)->toBe($affiliate->id)
        ->and($client->fresh()->referredBy->id)->toBe($affiliate->id);
});

test('visiting with a ref parameter queues the referral cookie and counts one visit', function () {
    $affiliate = affiliateFor();

    $this->get(route('client.login', ['ref' => $affiliate->id]))
        ->assertOk()
        ->assertCookie(AffiliateTracking::COOKIE, (string) $affiliate->id);

    expect($affiliate->fresh()->visitors)->toBe(1);
});

test('an unknown ref parameter is ignored', function () {
    $this->get(route('client.login', ['ref' => 999999]))
        ->assertOk()
        ->assertCookieMissing(AffiliateTracking::COOKIE);
});

test('registering with the referral cookie links the new client to the affiliate', function () {
    $affiliate = affiliateFor();

    $this->withCookie(AffiliateTracking::COOKIE, (string) $affiliate->id)
        ->post(route('client.register.submit'), [
            'first_name' => 'Refer', 'last_name' => 'Red',
            'email' => 'referred@example.com',
            'password' => 'Secret123!', 'password_confirmation' => 'Secret123!',
            'tos' => '1',
        ])->assertRedirect();

    expect(Client::where('email', 'referred@example.com')->value('affiliate_id'))->toBe($affiliate->id);
});

test('registering without a cookie leaves the client unreferred', function () {
    $this->post(route('client.register.submit'), [
        'first_name' => 'No', 'last_name' => 'Ref',
        'email' => 'noref@example.com',
        'password' => 'Secret123!', 'password_confirmation' => 'Secret123!',
        'tos' => '1',
    ])->assertRedirect();

    expect(Client::where('email', 'noref@example.com')->value('affiliate_id'))->toBeNull();
});

test('affiliates cannot refer themselves and an existing referral is not overwritten', function () {
    $affiliate = affiliateFor();
    $own = Client::find($affiliate->client_id);

    app(AffiliateService::class)->linkClientToAffiliate($own, $affiliate->id);
    expect($own->fresh()->affiliate_id)->toBeNull();

    $first = affiliateFor();
    $second = affiliateFor();
    $client = Client::factory()->create();

    app(AffiliateService::class)->linkClientToAffiliate($client, $first->id);
    app(AffiliateService::class)->linkClientToAffiliate($client, $second->id);

    expect($client->fresh()->affiliate_id)->toBe($first->id);
});

test('paying a referred client invoice credits the affiliate commission', function () {
    $affiliate = affiliateFor();
    $client = Client::factory()->create(['affiliate_id' => $affiliate->id]);
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => 200.00]);

    app(PaymentService::class)->applyPayment($invoice, 'stripe', 'TXN-AFF-1', 200.00);

    // 10% of 200
    expect((float) $affiliate->fresh()->balance)->toBe(20.0)
        ->and(Transaction::where('gateway', 'affiliate_commission')->where('invoice_id', $invoice->id)->exists())->toBeTrue();
});

test('paying an unreferred client invoice pays no commission', function () {
    $affiliate = affiliateFor();
    $client = Client::factory()->create();
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => 200.00]);

    app(PaymentService::class)->applyPayment($invoice, 'stripe', 'TXN-AFF-2', 200.00);

    expect((float) $affiliate->fresh()->balance)->toBe(0.0);
});

test('a one-time affiliate is only paid for the first invoice of a referred client', function () {
    $affiliate = affiliateFor();
    $affiliate->update(['onetime' => true]);
    $client = Client::factory()->create(['affiliate_id' => $affiliate->id]);

    $first = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => 100.00]);
    $second = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => 100.00]);

    app(PaymentService::class)->applyPayment($first, 'stripe', 'TXN-AFF-3', 100.00);
    app(PaymentService::class)->applyPayment($second, 'stripe', 'TXN-AFF-4', 100.00);

    expect((float) $affiliate->fresh()->balance)->toBe(10.0);
});

test('a flat rate affiliate earns its fixed amount', function () {
    $affiliate = affiliateFor();
    $affiliate->update(['pay_type' => 'flat', 'pay_amount' => 7.50]);
    $client = Client::factory()->create(['affiliate_id' => $affiliate->id]);
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => 999.00]);

    app(PaymentService::class)->applyPayment($invoice, 'stripe', 'TXN-AFF-5', 999.00);

    expect((float) $affiliate->fresh()->balance)->toBe(7.5);
});
