<?php

use App\Models\Client;
use App\Models\Currency;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use Modules\Gateways\Stripe\StripeModule;

/*
 * The Stripe card form wrote its button and messages in English whatever
 * the customer's language: "Pay 12.00", "Processing...", "Setup failed",
 * "Network error. Please try again.".
 */

function sflForm(string $locale): string
{
    Currency::updateOrCreate(['code' => 'EUR'], ['prefix' => '€', 'suffix' => '', 'rate' => 1, 'is_default' => true]);
    GatewaySettings::updateOrCreate(['gateway' => 'stripe', 'setting' => 'publishable_key'], ['value' => 'pk_test_x']);
    app()->setLocale($locale);
    $invoice = Invoice::factory()->create(['client_id' => Client::factory()->create()->id, 'status' => 'unpaid', 'total' => 12]);

    return app(StripeModule::class)->getPaymentForm($invoice);
}

test('the card form speaks the customer\'s language', function () {
    $form = sflForm('tr');

    expect($form)
        ->toContain('Kartla €12.00 öde')
        ->toContain('"İşleniyor…"')
        ->toContain('"Ödeme sağlayıcısına ulaşılamadı. Bağlantınızı kontrol edip tekrar deneyin."')
        ->not->toContain('Processing...')
        ->not->toContain('Setup failed')
        ->not->toContain('Network error');
});

test('the words are safe inside the script', function () {
    app('translator')->addLines(['messages.stripe.processing' => '</script><b>"x"'], 'en');

    $form = sflForm('en');

    expect($form)->not->toContain('</script><b>')->toContain('\u003C\/script\u003E\u003Cb\u003E\u0022x\u0022');
});

test('a form with no key says so in the customer\'s language', function () {
    app()->setLocale('tr');
    $invoice = Invoice::factory()->create(['client_id' => Client::factory()->create()->id, 'status' => 'unpaid', 'total' => 12]);

    expect(app(StripeModule::class)->getPaymentForm($invoice))->toContain('Kartla ödeme yapılandırılmamış');
});

test('the button names the amount in the currency the invoice is charged in', function () {
    Currency::updateOrCreate(['code' => 'TRY'], ['prefix' => '₺', 'suffix' => '', 'rate' => 40, 'is_default' => false]);
    GatewaySettings::updateOrCreate(['gateway' => 'stripe', 'setting' => 'publishable_key'], ['value' => 'pk_test_x']);
    app()->setLocale('en');
    $invoice = Invoice::factory()->create([
        'client_id' => Client::factory()->create()->id, 'status' => 'unpaid', 'total' => 480, 'source_currency' => 'TRY',
    ]);

    expect(app(StripeModule::class)->getPaymentForm($invoice))->toContain('Pay ₺480.00 by card');
});
