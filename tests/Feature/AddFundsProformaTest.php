<?php

use App\Models\Client;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use App\Models\Setting;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\ProformaService;
use Illuminate\Support\Facades\Mail;

/*
 * A top-up is a payment on account, not a supply: it follows the proforma
 * scheme (so the document says "proforma", under the proforma numbering) but
 * is never taxed, and paying it issues no VAT invoice — the money becomes
 * credit, and VAT is charged on the services that credit later pays.
 *
 * Before, the invoice number came from generateInvoiceNumber() (a PRO- series
 * when the proforma scheme was on) while type was left at the column default
 * 'vat', so the PDF was headed as an ordinary invoice.
 */

function fundsProformaUser(): User
{
    GatewaySettings::updateOrCreate(['gateway' => 'banktransfer', 'setting' => 'active'], ['value' => '1']);

    $client = Client::factory()->create(['credit' => 0]);
    $user = User::factory()->create();
    $user->clients()->attach($client->id);

    return $user;
}

function storeFundsTopUp(User $user, float $amount = 100): Invoice
{
    $client = $user->clients()->firstOrFail();

    test()->actingAs($user)->post(route('client.funds.store'), [
        'amount' => $amount,
        'payment_method' => 'banktransfer',
    ])->assertSessionHasNoErrors();

    return Invoice::where('client_id', $client->id)->latest('id')->firstOrFail();
}

test('a top-up follows the proforma scheme and is never taxed', function () {
    Mail::fake();
    Setting::set('ProformaEnabled', '1');

    $invoice = storeFundsTopUp(fundsProformaUser());

    expect($invoice->type)->toBe('proforma')
        ->and($invoice->isProforma())->toBeTrue()
        ->and((float) $invoice->tax)->toBe(0.0)
        ->and((float) $invoice->subtotal)->toBe(100.0)
        ->and((float) $invoice->total)->toBe(100.0)
        ->and(str_starts_with((string) $invoice->invoice_num, 'PRO-'))->toBeTrue();
});

test('paying a top-up proforma issues no VAT invoice and credits the account', function () {
    Mail::fake();
    Setting::set('ProformaEnabled', '1');
    $user = fundsProformaUser();
    $client = $user->clients()->firstOrFail();
    $invoice = storeFundsTopUp($user);

    app(PaymentService::class)->applyPayment($invoice->fresh(), 'banktransfer', 'TXN-TOPUP', 100.0);

    expect($invoice->fresh()->status)->toBe('paid')
        ->and((float) $client->fresh()->credit)->toBe(100.0)
        // A payment on account has no supply to invoice yet.
        ->and(Invoice::where('source_invoice_id', $invoice->id)->exists())->toBeFalse()
        ->and(app(ProformaService::class)->issueVatInvoice($invoice->fresh()))->toBeNull();
});

test('without the proforma scheme a top-up is a plain untaxed VAT invoice', function () {
    Mail::fake();
    Setting::set('ProformaEnabled', '0');

    $invoice = storeFundsTopUp(fundsProformaUser());

    expect($invoice->type)->toBe('vat')
        ->and($invoice->isProforma())->toBeFalse()
        ->and((float) $invoice->tax)->toBe(0.0)
        ->and(str_starts_with((string) $invoice->invoice_num, 'PRO-'))->toBeFalse();
});
