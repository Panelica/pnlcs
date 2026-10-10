<?php

use App\Models\Client;
use App\Models\Currency;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use Illuminate\Support\Facades\Http;
use Modules\Gateways\Stripe\StripeModule;

/*
 * "Charge in the invoice's billing currency" (Stripe, off by default). A euro
 * shop billing a customer in lira at 40: the card is charged ₺400.00 for a
 * €10.00 invoice, so the customer's statement shows what they saw, and every
 * figure that comes back is recorded in euros.
 */

function sbcConfigured(bool $billingCurrency = true): void
{
    foreach (['publishable_key' => 'pk_test_x', 'secret_key' => 'sk_test_x', 'webhook_secret' => 'whsec_test', 'charge_billing_currency' => $billingCurrency ? '1' : '0'] as $setting => $value) {
        GatewaySettings::updateOrCreate(['gateway' => 'stripe', 'setting' => $setting], ['value' => $value]);
    }
    Currency::updateOrCreate(['code' => 'EUR'], ['prefix' => '€', 'suffix' => '', 'rate' => 1, 'is_default' => true]);
    Currency::updateOrCreate(['code' => 'TRY'], ['prefix' => '₺', 'suffix' => '', 'rate' => 40, 'is_default' => false]);
    app()->forgetInstance('pnlcs.currency');
}

function sbcInvoice(float $total = 10.0, bool $inLira = true): Invoice
{
    return Invoice::factory()->create(array_merge([
        'client_id' => Client::factory()->create()->id, 'status' => 'unpaid', 'total' => $total, 'source_currency' => 'EUR',
    ], $inLira ? ['billing_currency' => 'TRY', 'exchange_rate' => 40] : ['billing_currency' => null, 'exchange_rate' => null]));
}

function sbcIntent(Invoice $invoice, int $received, array $meta = ['source_currency' => 'eur', 'source_amount' => '10.00', 'rate' => '40']): array
{
    return ['id' => 'pi_try', 'status' => 'succeeded', 'currency' => 'try', 'amount_received' => $received,
        'metadata' => ['invoice_id' => (string) $invoice->id] + $meta];
}

test('switched off, a lira-billed invoice is charged in euros as before', function () {
    sbcConfigured(false);
    Http::fake(['*' => Http::response(['id' => 'pi_eur', 'client_secret' => 's'], 200)]);

    app(StripeModule::class)->capture(sbcInvoice(), 10.0);

    Http::assertSent(fn ($r) => $r['currency'] === 'eur' && $r['amount'] === 1000 && ! isset($r['metadata[rate]']));
});

test('switched on, it is charged in lira at the invoice\'s rate, and the intent says what it stands for', function () {
    sbcConfigured();
    Http::fake(['*' => Http::response(['id' => 'pi_try', 'client_secret' => 's'], 200)]);

    app(StripeModule::class)->capture(sbcInvoice(), 10.0);

    Http::assertSent(fn ($r) => $r['currency'] === 'try' && $r['amount'] === 40000
        && $r['metadata[source_currency]'] === 'eur' && $r['metadata[source_amount]'] === '10.00' && $r['metadata[rate]'] === '40');
});

test('an invoice billed in the shop currency is charged in it either way', function () {
    sbcConfigured();
    Http::fake(['*' => Http::response(['id' => 'pi_eur', 'client_secret' => 's'], 200)]);

    app(StripeModule::class)->capture(sbcInvoice(10.0, false), 10.0);

    Http::assertSent(fn ($r) => $r['currency'] === 'eur' && $r['amount'] === 1000);
});

test('the card form is set up for the same lira amount, and its button names it', function () {
    sbcConfigured();
    app()->setLocale('en');

    $form = app(StripeModule::class)->getPaymentForm(sbcInvoice());

    expect($form)->toContain('amount: 40000')->toContain('currency: "try"')->toContain('Pay ₺400.00 by card');
});

test('a lira payment in full is recorded as the euro amount, to the cent', function () {
    sbcConfigured();
    $invoice = sbcInvoice();
    Http::fake(['*' => Http::response(sbcIntent($invoice, 40000), 200)]);

    $verified = app(StripeModule::class)->verifyPaymentIntent('pi_try', $invoice->id);

    expect($verified['success'])->toBeTrue()->and((float) $verified['amount'])->toBe(10.0);
});

test('a part of it is converted back at the same rate', function () {
    sbcConfigured();
    $invoice = sbcInvoice();
    Http::fake(['*' => Http::response(sbcIntent($invoice, 20000), 200)]);

    expect((float) app(StripeModule::class)->verifyPaymentIntent('pi_try', $invoice->id)['amount'])->toBe(5.0);
});

test('the webhook records the lira payment in euros and pays the invoice', function () {
    sbcConfigured();
    $invoice = sbcInvoice();
    $body = json_encode(['id' => 'evt_try', 'type' => 'payment_intent.succeeded', 'data' => ['object' => sbcIntent($invoice, 40000)]]);
    $t = time();

    $this->call('POST', '/gateway/stripe/webhook', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => "t={$t},v1=".hash_hmac('sha256', $t.'.'.$body, 'whsec_test'),
    ], $body)->assertOk();

    expect($invoice->fresh()->status)->toBe('paid')
        ->and((float) \App\Models\Transaction::where('transaction_id', 'pi_try')->value('amount_in'))->toBe(10.0);
});

test('a refund in euros goes back in lira: in full exactly what was taken, in part at the rate', function () {
    sbcConfigured();
    $invoice = sbcInvoice();
    Http::fake([
        'api.stripe.com/v1/payment_intents/*' => Http::response(sbcIntent($invoice, 40000), 200),
        'api.stripe.com/v1/refunds' => Http::response(['id' => 're_1', 'status' => 'succeeded'], 200),
    ]);

    \App\Models\Transaction::create(['client_id' => $invoice->client_id, 'invoice_id' => $invoice->id, 'gateway' => 'stripe', 'date' => now(), 'amount_in' => 10, 'transaction_id' => 'pi_try', 'description' => 'Invoice payment']);

    app(StripeModule::class)->refund('pi_try', 10.0);
    app(StripeModule::class)->refund('pi_try', 2.5);

    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/v1/refunds') && $r['amount'] === 40000);
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/v1/refunds') && $r['amount'] === 10000);
});

test('a renewal charged from a stored card is charged in lira too, and reported in euros', function () {
    sbcConfigured();
    $invoice = sbcInvoice();
    $card = \App\Models\PaymentMethod::create([
        'client_id' => $invoice->client_id, 'gateway_name' => 'stripe', 'payment_type' => 'cc',
        'remote_token' => 'pm_1', 'gateway_customer_id' => 'cus_1', 'last_four' => '4242', 'expiry_date' => '2030-07',
    ]);
    Http::fake(['*' => Http::response(['id' => 'pi_renew', 'status' => 'succeeded', 'amount_received' => 40000, 'currency' => 'try'], 200)]);

    $result = app(StripeModule::class)->chargeStoredMethod($invoice, $card, 10.0, ['idempotency_key' => 'k1']);

    expect($result['status'])->toBe('succeeded')->and((float) $result['amount'])->toBe(10.0);
    Http::assertSent(fn ($r) => $r['currency'] === 'try' && $r['amount'] === 40000 && $r['off_session'] === 'true'
        && $r['metadata[source_currency]'] === 'eur' && $r['metadata[rate]'] === '40');
});

test('switched off, a renewal is charged in the shop currency as before', function () {
    sbcConfigured(false);
    $invoice = sbcInvoice();
    $card = \App\Models\PaymentMethod::create([
        'client_id' => $invoice->client_id, 'gateway_name' => 'stripe', 'payment_type' => 'cc',
        'remote_token' => 'pm_1', 'gateway_customer_id' => 'cus_1', 'last_four' => '4242', 'expiry_date' => '2030-07',
    ]);
    Http::fake(['*' => Http::response(['id' => 'pi_renew', 'status' => 'succeeded', 'amount_received' => 1000, 'currency' => 'eur'], 200)]);

    $result = app(StripeModule::class)->chargeStoredMethod($invoice, $card, 10.0, ['idempotency_key' => 'k2']);

    expect((float) $result['amount'])->toBe(10.0);
    Http::assertSent(fn ($r) => $r['currency'] === 'eur' && $r['amount'] === 1000 && ! isset($r['metadata[rate]']));
});
