<?php

use App\Models\Client;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use Illuminate\Support\Facades\Http;
use Modules\Gateways\Stripe\StripeModule;

/*
 * The Stripe pay form used the bare card field, so a customer on a phone with
 * Apple Pay or Google Pay still typed a card number. It is now the Payment
 * Element, which shows the wallets where the device has them (both are cards
 * to Stripe), and BLIK in złoty. It is mounted on an intent made when the form
 * opens: set up before the intent, Stripe dropped BLIK without a word.
 */

function speForm(array $invoice = [], string $locale = 'en'): string
{
    GatewaySettings::updateOrCreate(['gateway' => 'stripe', 'setting' => 'publishable_key'], ['value' => 'pk_test_x']);
    app()->setLocale($locale);

    return app(StripeModule::class)->getPaymentForm(Invoice::factory()->create($invoice + [
        'client_id' => Client::factory()->create()->id, 'status' => 'unpaid', 'total' => 12.5,
    ]));
}

test('the form is the Payment Element, mounted on the intent it asks for', function () {
    $form = speForm(['source_currency' => 'EUR'], 'tr');

    expect($form)
        ->toContain('elements.create("payment"')
        ->toContain('stripe.elements({ clientSecret: data.client_secret, locale: "tr" })')
        ->toContain('/gateway/stripe/intent/')
        ->toContain('stripe.confirmPayment(')
        ->toContain('redirect: "if_required"')
        ->not->toContain('mode: "payment"')
        ->not->toContain('elements.create("card"')
        ->not->toContain('confirmCardPayment');
});

test('the intent asked for is what is owed, in minor units', function () {
    $invoice = Invoice::factory()->create(['client_id' => Client::factory()->create()->id, 'status' => 'unpaid', 'total' => 12.5, 'source_currency' => 'EUR']);
    GatewaySettings::updateOrCreate(['gateway' => 'stripe', 'setting' => 'secret_key'], ['value' => 'sk_test_x']);
    Http::fake(['api.stripe.com/v1/payment_intents' => Http::response(['id' => 'pi_1', 'client_secret' => 'pi_1_secret'])]);

    app(StripeModule::class)->capture($invoice, $invoice->amountDue());

    Http::assertSent(fn ($request) => $request['amount'] == 1250 && $request['currency'] === 'eur' && str_contains($request->body(), 'payment_method_types%5B%5D=card'));
});

test('in złoty the intent offers BLIK beside the card', function () {
    $invoice = Invoice::factory()->create(['client_id' => Client::factory()->create()->id, 'status' => 'unpaid', 'total' => 12.5, 'source_currency' => 'PLN']);
    GatewaySettings::updateOrCreate(['gateway' => 'stripe', 'setting' => 'secret_key'], ['value' => 'sk_test_x']);
    Http::fake(['api.stripe.com/v1/payment_intents' => Http::response(['id' => 'pi_1', 'client_secret' => 'pi_1_secret'])]);

    app(StripeModule::class)->capture($invoice, $invoice->amountDue());

    Http::assertSent(fn ($request) => $request['currency'] === 'pln' && $request['payment_method_types'] === ['card', 'blik']);
});

test('a złoty payment that keeps its card stays card-only', function () {
    $client = Client::factory()->create();
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => 12.5, 'source_currency' => 'PLN']);
    GatewaySettings::updateOrCreate(['gateway' => 'stripe', 'setting' => 'secret_key'], ['value' => 'sk_test_x']);
    Http::fake([
        'api.stripe.com/v1/customers*' => Http::response(['id' => 'cus_1']),
        'api.stripe.com/v1/payment_intents' => Http::response(['id' => 'pi_1', 'client_secret' => 'pi_1_secret']),
    ]);

    app(StripeModule::class)->capture($invoice, $invoice->amountDue(), ['save_card' => true]);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/payment_intents')
        && $request['setup_future_usage'] === 'off_session'
        && $request['payment_method_types[]'] === 'card'
        && ! str_contains($request->body(), 'blik'));
});

test('after Stripe has the payment, a failed confirm says to check the invoice and keeps the button off', function () {
    // The confirm request used to be left out of the promise chain: a 500 or a
    // non-JSON answer never reached the catch, the button stayed on
    // "Processing" and nothing was said, with the card already charged.
    $form = speForm(['source_currency' => 'EUR'], 'tr');

    expect($form)
        ->toContain('return fetch(')
        ->toContain('taken = true;')
        ->toContain('if (taken) {')
        ->toContain('function settled(message)')
        ->toContain(json_encode(__('client.invoices.payment_maybe_taken'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT))
        ->not->toContain('.innerHTML = "<div class=\"alert alert-danger\">" + (res.message');
    expect(__('client.invoices.payment_maybe_taken'))->toContain('faturayı yenileyin');
});
