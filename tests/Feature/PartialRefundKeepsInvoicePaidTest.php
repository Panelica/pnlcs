<?php

use App\Enums\InvoiceStatus;
use App\Events\InvoicePaid;
use App\Models\Client;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use App\Models\Setting;
use App\Models\User;
use App\Services\InvoiceGenerationService;
use App\Services\InvoicePdfService;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Event;

/*
 * Giving back part of what was paid on an invoice does not make the invoice
 * owed again. A partial refund used to move a paid invoice to partially_paid,
 * which is an open status: past its due date it was marked overdue, and from
 * there the reminders, the late fee, the card on file and the suspension job
 * went after the amount the operator had just handed back. The customer was
 * offered to pay it again, and paying it fired InvoicePaid a second time,
 * which on a renewal invoice renews the service for another cycle.
 */
function partlyRefundedSetup(float $total = 100.0, string $dueDate = '-30 days'): array
{
    $client = Client::factory()->create(['tax_exempt' => true]);
    $invoice = app(InvoiceService::class)->createInvoice($client, [
        ['type' => 'Hosting', 'rel_id' => 0, 'description' => 'A month of hosting', 'amount' => $total, 'taxed' => false],
    ], ['due_date' => now()->modify($dueDate)->toDateString()]);

    return [$client, $invoice->fresh()];
}

test('a paid invoice partly refunded is still paid', function () {
    [, $invoice] = partlyRefundedSetup();
    app(PaymentService::class)->applyPayment($invoice, 'banktransfer', 'BT-PR-1', 100.0);

    $result = app(PaymentService::class)->refundInvoice($invoice->fresh(), 30.0, ['gateway_refund' => false]);

    expect($result['success'])->toBeTrue()
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid->value)
        ->and(app(PaymentService::class)->amountPaid($invoice->fresh()))->toBe(70.0);
});

test('a paid invoice partly refunded past its due date is not marked overdue or listed as owed', function () {
    [$client, $invoice] = partlyRefundedSetup();
    app(PaymentService::class)->applyPayment($invoice, 'banktransfer', 'BT-PR-2', 100.0);
    app(PaymentService::class)->refundInvoice($invoice->fresh(), 30.0, ['gateway_refund' => false]);

    app(InvoiceGenerationService::class)->markOverdueInvoices();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid->value)
        ->and(Invoice::where('client_id', $client->id)->outstanding()->count())->toBe(0);
});

test('money arriving on a partly refunded invoice does not settle it a second time', function () {
    [$client, $invoice] = partlyRefundedSetup();
    app(PaymentService::class)->applyPayment($invoice, 'banktransfer', 'BT-PR-3', 100.0);
    app(PaymentService::class)->refundInvoice($invoice->fresh(), 30.0, ['gateway_refund' => false]);

    Event::fake([InvoicePaid::class]);
    app(PaymentService::class)->applyPayment($invoice->fresh(), 'banktransfer', 'BT-PR-4', 30.0);

    Event::assertNotDispatched(InvoicePaid::class);
    expect((float) $client->fresh()->credit)->toBe(30.0)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid->value);
});

test('an invoice paid out of the balance and partly refunded is still paid', function () {
    $client = Client::factory()->create(['tax_exempt' => true, 'credit' => 100]);
    $invoice = app(InvoiceService::class)->createInvoice($client, [
        ['type' => 'Hosting', 'rel_id' => 0, 'description' => 'A month of hosting', 'amount' => 100, 'taxed' => false],
    ]);
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid->value);

    app(PaymentService::class)->refundInvoice($invoice->fresh(), 40.0, ['gateway_refund' => false]);

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid->value)
        ->and((float) $client->fresh()->credit)->toBe(40.0);
});

test('the customer\'s page shows no amount left to pay on a partly refunded invoice', function () {
    [$client, $invoice] = partlyRefundedSetup();
    $user = User::factory()->create();
    $user->clients()->attach($client->id, ['owner' => true]);
    app(PaymentService::class)->applyPayment($invoice, 'banktransfer', 'BT-PR-5', 100.0);
    app(PaymentService::class)->refundInvoice($invoice->fresh(), 30.0, ['gateway_refund' => false]);

    $this->actingAs($user)->get(route('client.invoices.show', $invoice))
        ->assertOk()
        ->assertDontSee(__('client.invoices.remaining_balance'))
        ->assertDontSee(__('client.invoices.pay_this_invoice'));
});

test('the PDF of a partly refunded invoice carries no payment QR', function () {
    // A Polish shop with a złoty account draws a ZBP transfer code, amount
    // included, on any invoice that is still owed.
    Setting::set('Country', 'PL', 'general');
    foreach ([
        'active' => '1',
        'account_name' => 'Test Shop',
        'bank_active' => '1',
        'bank_name' => 'Test Bank',
        'bank_currency' => 'PLN',
        'iban' => 'PL 63 2530 0008 2096 1022 3191 0001',
    ] as $setting => $value) {
        GatewaySettings::updateOrCreate(['gateway' => 'banktransfer', 'setting' => $setting], ['value' => $value]);
    }
    [, $invoice] = partlyRefundedSetup();
    $pdf = app(InvoicePdfService::class);
    expect($pdf->paymentQr($invoice->fresh()))->toStartWith('<img src="data:image/png;base64,');

    app(PaymentService::class)->applyPayment($invoice, 'banktransfer', 'BT-PR-8', 100.0);
    app(PaymentService::class)->refundInvoice($invoice->fresh(), 30.0, ['gateway_refund' => false]);

    expect($pdf->paymentQr($invoice->fresh()))->toBe('');
});

test('refunding the rest of a partly refunded invoice marks it refunded', function () {
    [, $invoice] = partlyRefundedSetup();
    app(PaymentService::class)->applyPayment($invoice, 'banktransfer', 'BT-PR-6', 100.0);
    app(PaymentService::class)->refundInvoice($invoice->fresh(), 30.0, ['gateway_refund' => false]);
    app(PaymentService::class)->refundInvoice($invoice->fresh(), 70.0, ['gateway_refund' => false]);

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Refunded->value);
});

test('an invoice that was only part paid stays part paid after a refund', function () {
    [, $invoice] = partlyRefundedSetup();
    app(PaymentService::class)->applyPayment($invoice, 'banktransfer', 'BT-PR-7', 50.0);

    app(PaymentService::class)->refundInvoice($invoice->fresh(), 20.0, ['gateway_refund' => false]);

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::PartiallyPaid->value)
        ->and(app(PaymentService::class)->balance($invoice->fresh()))->toBe(70.0);
});
