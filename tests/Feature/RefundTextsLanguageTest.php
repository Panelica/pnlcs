<?php

use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Language;
use App\Models\Setting;
use App\Models\Transaction;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Mail;

/*
 * #455. A refund told the admin "Refund of 50.00 processed." in English
 * whatever their language, the payment and refund rows on the customer's
 * history were written in English, and the activity log named an invoice by
 * its database id, a number shown nowhere else. The admin's messages follow
 * the admin's language; text kept on the customer's records is written in
 * the customer's language, the way the proforma reference already is.
 */
beforeEach(function () {
    Mail::fake();
    foreach (['en' => 'English', 'tr' => 'Türkçe', 'de' => 'Deutsch'] as $code => $name) {
        Language::updateOrCreate(['code' => $code], ['name' => $name, 'native_name' => $name, 'is_active' => true, 'is_default' => $code === 'en']);
    }
});

function refundTextsInvoice(string $clientLanguage): Invoice
{
    $client = Client::factory()->create(['language' => $clientLanguage]);
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'subtotal' => 50, 'total' => 50]);
    app(PaymentService::class)->applyPayment($invoice, 'banktransfer', 'BT-455', 50.0);

    return $invoice->fresh();
}

test('the refund message is in the admin\'s language', function () {
    $invoice = refundTextsInvoice('de');
    $admin = Admin::factory()->create(['language' => 'tr']);

    $this->actingAs($admin, 'admin')
        ->from(route('admin.invoices.show', $invoice))
        ->post(route('admin.invoices.refund', $invoice).'?lang=tr', ['amount' => 50, 'gateway_refund' => 0])
        ->assertSessionHas('success', '50.00 tutarındaki iade işlendi.');
});

test('a refused refund says why in the admin\'s language', function () {
    $client = Client::factory()->create();
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => 50]);
    $admin = Admin::factory()->create(['language' => 'tr']);

    $this->actingAs($admin, 'admin')
        ->from(route('admin.invoices.show', $invoice))
        ->post(route('admin.invoices.refund', $invoice).'?lang=tr', ['gateway_refund' => 0])
        ->assertSessionHas('error', 'Bu faturaya henüz ödeme yapılmadı.');
});

test('the payment and refund rows are written in the customer\'s language', function () {
    $invoice = refundTextsInvoice('de');

    app()->setLocale('tr'); // the admin who refunds reads Turkish
    app(PaymentService::class)->refundInvoice($invoice, 50.0, ['gateway_refund' => false]);

    $rows = Transaction::where('invoice_id', $invoice->id)->orderBy('id')->pluck('description')->all();

    expect($rows[0])->toBe("Zahlung für Rechnung #{$invoice->invoice_num}")
        ->and($rows[1])->toBe("Erstattung für Rechnung #{$invoice->invoice_num}");
});

test('a customer with no language recorded gets the shop\'s default', function () {
    Setting::set('DefaultLanguage', 'de');
    $invoice = refundTextsInvoice('');

    expect(Transaction::where('invoice_id', $invoice->id)->value('description'))
        ->toBe("Zahlung für Rechnung #{$invoice->invoice_num}");
});

test('balance handed back by a refund is described in the customer\'s language', function () {
    $client = Client::factory()->create(['language' => 'tr', 'credit' => 0]);
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'paid', 'total' => 20, 'credit' => 20]);

    app()->setLocale('en');
    app(PaymentService::class)->refundInvoice($invoice, null, ['gateway_refund' => false]);

    expect(Credit::where('client_id', $client->id)->latest('id')->value('description'))
        ->toBe("#{$invoice->invoice_num} numaralı faturanın iadesi — bakiye geri verildi");
});

test('the activity log names an invoice by its number', function () {
    $invoice = refundTextsInvoice('en');

    $paid = ActivityLog::where('invoice_id', $invoice->id)->latest('id')->value('description');

    expect($paid)->toBe("Invoice #{$invoice->invoice_num} paid")
        ->and($invoice->invoice_num)->not->toBe((string) $invoice->id);
});

test('balance bought with an Add Funds invoice is described in the customer\'s language', function () {
    $client = Client::factory()->create(['language' => 'de', 'credit' => 0]);
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'subtotal' => 30, 'total' => 30]);
    $invoice->items()->create(['client_id' => $client->id, 'type' => 'AddFunds', 'description' => 'Add Funds', 'amount' => 30]);

    app()->setLocale('en');
    app(PaymentService::class)->applyPayment($invoice, 'banktransfer', 'BT-455-F', 30.0);

    expect(Credit::where('client_id', $client->id)->latest('id')->value('description'))
        ->toBe("Guthaben aufgeladen über Rechnung #{$invoice->invoice_num}");
});
