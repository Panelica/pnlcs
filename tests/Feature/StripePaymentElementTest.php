<?php

use App\Models\Client;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use Illuminate\Support\Facades\Http;
use Modules\Gateways\Stripe\StripeModule;

/*
 * The Stripe pay form used the bare card field, so a customer on a phone with
 * Apple Pay or Google Pay still typed a card number. It is now the Payment
 * Element, which shows the wallets where the device has them. The intent stays
 * card-only (both wallets are cards to Stripe), so what is recorded, refunded
 * and charged later does not change.
 */

function speForm(array $invoice = [], string $locale = 'en'): string
{
    GatewaySettings::updateOrCreate(['gateway' => 'stripe', 'setting' => 'publishable_key'], ['value' => 'pk_test_x']);
    app()->setLocale($locale);

    return app(StripeModule::class)->getPaymentForm(Invoice::factory()->create($invoice + [
        'client_id' => Client::factory()->create()->id, 'status' => 'unpaid', 'total' => 12.5,
    ]));
}

test('the form is the Payment Element, card-only, for what is owed in minor units', function () {
    $form = speForm(['source_currency' => 'EUR'], 'tr');

    expect($form)
        ->toContain('elements.create("payment"')
        ->toContain('paymentMethodTypes: ["card"]')
        ->toContain('amount: 1250,')
        ->toContain('currency: "eur",')
        ->toContain('locale: "tr"')
        ->toContain('stripe.confirmPayment(')
        ->toContain('redirect: "if_required"')
        ->not->toContain('elements.create("card"')
        ->not->toContain('confirmCardPayment');
});

test('a zero-decimal currency is not multiplied by a hundred', function () {
    expect(speForm(['source_currency' => 'JPY', 'total' => 500]))->toContain('amount: 500,')->toContain('currency: "jpy",');
});

test('the intent asked for at payment is the amount the form was set up with', function () {
    $invoice = Invoice::factory()->create(['client_id' => Client::factory()->create()->id, 'status' => 'unpaid', 'total' => 12.5, 'source_currency' => 'EUR']);
    GatewaySettings::updateOrCreate(['gateway' => 'stripe', 'setting' => 'secret_key'], ['value' => 'sk_test_x']);
    GatewaySettings::updateOrCreate(['gateway' => 'stripe', 'setting' => 'publishable_key'], ['value' => 'pk_test_x']);
    Http::fake(['api.stripe.com/v1/payment_intents' => Http::response(['id' => 'pi_1', 'client_secret' => 'pi_1_secret'])]);

    $form = app(StripeModule::class)->getPaymentForm($invoice);
    app(StripeModule::class)->capture($invoice, $invoice->amountDue());

    Http::assertSent(fn ($request) => $request['amount'] == 1250 && $request['currency'] === 'eur' && str_contains($request->body(), 'payment_method_types%5B%5D=card'));
    expect($form)->toContain('amount: 1250,');
});
