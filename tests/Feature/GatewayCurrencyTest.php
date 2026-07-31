<?php

use App\Models\Client;
use App\Models\Currency;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use Illuminate\Support\Facades\Http;
use Modules\Gateways\AuthorizeNet\AuthorizeNetModule;
use Modules\Gateways\BankTransfer\BankTransferModule;
use Modules\Gateways\Mollie\MollieModule;
use Modules\Gateways\PayPal\PayPalModule;
use Modules\Gateways\Razorpay\RazorpayModule;
use Modules\Gateways\Stripe\StripeModule;

/**
 * The currency an invoice is charged in.
 *
 * Nothing ever told a gateway what currency the shop sells in, so each module
 * fell back to one of its own: PayPal to USD, Mollie to EUR, Razorpay to INR,
 * Stripe to usd. The same invoice was therefore charged in a different
 * currency depending on which button the customer pressed - a 100.00 invoice
 * became EUR 100 through Mollie and INR 100 through Razorpay - and the payment
 * buttons printed a euro or a rupee sign over an amount that was neither.
 */
beforeEach(function () {
    Currency::query()->update(['is_default' => false]);
    Currency::updateOrCreate(
        ['code' => 'GBP'],
        ['prefix' => '£', 'suffix' => '', 'rate' => 1, 'is_default' => true]
    );

    // money_fmt() memoises the currency for the request.
    app()->forgetInstance('pnlcs.currency');
});

function shopInvoice(float $total = 100.0): Invoice
{
    return Invoice::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'invoice_num' => 'INV-CUR-1',
        'status' => 'unpaid',
        'total' => $total,
    ]);
}

function gatewayKey(string $gateway, string $setting = 'api_key', string $value = 'test-key'): void
{
    GatewaySettings::updateOrCreate(
        ['gateway' => $gateway, 'setting' => $setting],
        ['value' => $value]
    );
}

test('mollie is asked for the currency the shop sells in', function () {
    gatewayKey('mollie');
    Http::fake(['api.mollie.com/*' => Http::response(['id' => 'tr_1', '_links' => ['checkout' => ['href' => 'https://pay']]], 201)]);

    app(MollieModule::class)->capture(shopInvoice(), 100.0);

    Http::assertSent(fn ($request) => $request['amount']['currency'] === 'GBP');
});

test('paypal is asked for the currency the shop sells in', function () {
    gatewayKey('paypal', 'client_id', 'id');
    gatewayKey('paypal', 'client_secret', 'secret');
    Http::fake([
        '*/v1/oauth2/token' => Http::response(['access_token' => 'token'], 200),
        '*' => Http::response(['id' => 'ORDER-1', 'links' => [['rel' => 'approve', 'href' => 'https://pay']]], 201),
    ]);

    app(PayPalModule::class)->capture(shopInvoice(), 100.0);

    // The order request has to be found, not merely not-contradicted: before
    // the fix this assertion passed on the token request alone.
    $seen = false;

    Http::assertSent(function ($request) use (&$seen) {
        $units = $request['purchase_units'] ?? null;

        if ($units && ($units[0]['amount']['currency_code'] ?? null) === 'GBP') {
            $seen = true;
        }

        return true;
    });

    expect($seen)->toBeTrue();
});

test('razorpay is asked for the currency the shop sells in', function () {
    gatewayKey('razorpay', 'key_id', 'id');
    gatewayKey('razorpay', 'key_secret', 'secret');
    Http::fake(['*' => Http::response(['id' => 'order_1'], 200)]);

    app(RazorpayModule::class)->capture(shopInvoice(), 100.0);

    Http::assertSent(fn ($request) => ($request['currency'] ?? 'GBP') === 'GBP');
});

test('stripe is asked for the currency the shop sells in', function () {
    gatewayKey('stripe', 'secret_key', 'sk_test');
    Http::fake(['*' => Http::response(['id' => 'pi_1', 'client_secret' => 'cs_1'], 200)]);

    app(StripeModule::class)->capture(shopInvoice(), 100.0);

    Http::assertSent(fn ($request) => ($request['currency'] ?? 'gbp') === 'gbp');
});

test('a payment button does not print a currency the shop does not sell in', function () {
    gatewayKey('mollie');
    $invoice = shopInvoice(100.0);

    $form = app(MollieModule::class)->getPaymentForm($invoice);

    expect($form)->toContain('£100.00')
        ->and($form)->not->toContain('&euro;');
});

test('the razorpay button does not print a rupee sign over a sterling amount', function () {
    gatewayKey('razorpay', 'key_id', 'id');
    $invoice = shopInvoice(100.0);

    $form = app(RazorpayModule::class)->getPaymentForm($invoice);

    expect($form)->toContain('£100.00')
        ->and($form)->not->toContain('₹100.00');
});

test('the paypal buttons load in the currency the shop sells in', function () {
    gatewayKey('paypal', 'client_id', 'id');
    gatewayKey('paypal', 'client_secret', 'secret');
    $invoice = shopInvoice(100.0);

    $form = app(PayPalModule::class)->getPaymentForm($invoice);

    expect($form)->toContain('currency=GBP')
        ->and($form)->not->toContain('currency=USD');
});

test('the remaining buttons print the amount in the currency the shop sells in', function () {
    gatewayKey('authorize', 'api_login_id', 'login');
    gatewayKey('authorize', 'transaction_key', 'key');
    gatewayKey('authorize', 'client_key', 'ckey');
    $invoice = shopInvoice(100.0);

    expect(app(AuthorizeNetModule::class)->getPaymentForm($invoice))
        ->toContain('£100.00')
        ->and(app(BankTransferModule::class)->getPaymentForm($invoice))
        ->toContain('£100.00');
});
