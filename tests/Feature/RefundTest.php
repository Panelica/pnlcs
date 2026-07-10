<?php

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    GatewaySettings::create(['gateway' => 'stripe', 'setting' => 'secret_key', 'value' => 'sk_test_123']);
});

function paidInvoice(string $gateway, string $txnId, float $total = 100.0): Invoice
{
    $client  = Client::factory()->create();
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => $total]);
    app(PaymentService::class)->applyPayment($invoice, $gateway, $txnId, $total);

    return $invoice->fresh();
}

// ---------------------------------------------------------------------------
// PaymentService::refundInvoice
// ---------------------------------------------------------------------------

test('full gateway refund reverses the payment and marks the invoice refunded', function () {
    Http::fake(['api.stripe.com/*' => Http::response(['id' => 're_123', 'status' => 'succeeded'], 200)]);

    $invoice = paidInvoice('stripe', 'pi_abc', 100.0);

    $result = app(PaymentService::class)->refundInvoice($invoice, null, ['reason' => 'customer request']);

    expect($result['success'])->toBeTrue()
        ->and($result['amount'])->toBe(100.0)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Refunded->value)
        ->and(app(PaymentService::class)->amountPaid($invoice->fresh()))->toBe(0.0);

    Http::assertSent(fn ($r) => str_contains($r->url(), 'api.stripe.com/v1/refunds')
        && ($r->data()['payment_intent'] ?? null) === 'pi_abc');
});

test('partial refund leaves the invoice partially paid', function () {
    Http::fake(['api.stripe.com/*' => Http::response(['id' => 're_partial'], 200)]);

    $invoice = paidInvoice('stripe', 'pi_partial', 100.0);

    $result = app(PaymentService::class)->refundInvoice($invoice, 30.0);

    expect($result['success'])->toBeTrue()
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::PartiallyPaid->value)
        ->and(app(PaymentService::class)->amountPaid($invoice->fresh()))->toBe(70.0);
});

test('refund cannot exceed the amount paid', function () {
    Http::fake();
    $invoice = paidInvoice('manual', 'MAN-1', 50.0);

    $result = app(PaymentService::class)->refundInvoice($invoice, 80.0);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('exceeds');
    Http::assertNothingSent();
});

test('refund on an unpaid invoice fails', function () {
    $client  = Client::factory()->create();
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => 40.0]);

    $result = app(PaymentService::class)->refundInvoice($invoice);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('Nothing has been paid');
});

test('offline gateways skip the gateway API but still record the refund', function () {
    Http::fake();
    $invoice = paidInvoice('banktransfer', 'BT-1', 60.0);

    $result = app(PaymentService::class)->refundInvoice($invoice);

    expect($result['success'])->toBeTrue()
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Refunded->value)
        ->and(Transaction::where('invoice_id', $invoice->id)->where('amount_out', '>', 0)->count())->toBe(1);
    Http::assertNothingSent();
});

test('gateway refund failure aborts and does not touch the invoice', function () {
    Http::fake(['api.stripe.com/*' => Http::response(['error' => ['message' => 'charge already refunded']], 400)]);

    $invoice = paidInvoice('stripe', 'pi_fail', 100.0);

    $result = app(PaymentService::class)->refundInvoice($invoice);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('Gateway refund failed')
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid->value)
        ->and(Transaction::where('invoice_id', $invoice->id)->where('amount_out', '>', 0)->count())->toBe(0);
});

test('RefundIssued hook fires with the refunded amount', function () {
    Http::fake(['api.stripe.com/*' => Http::response(['id' => 're_hook'], 200)]);
    $invoice = paidInvoice('stripe', 'pi_hook', 25.0);

    $seen = null;
    add_hook('RefundIssued', function (array $vars) use (&$seen) { $seen = $vars; });

    app(PaymentService::class)->refundInvoice($invoice);

    expect($seen)->not->toBeNull()
        ->and((float) $seen['amount'])->toBe(25.0)
        ->and($seen['gateway'])->toBe('stripe');
});

// ---------------------------------------------------------------------------
// Admin endpoint
// ---------------------------------------------------------------------------

test('admin refund endpoint processes an offline refund', function () {
    $admin = \App\Models\Admin::factory()->create(['role_id' => \App\Models\AdminRole::factory()->fullAdmin()->create()->id]);
    $invoice = paidInvoice('banktransfer', 'BT-ADMIN', 90.0);

    $this->actingAs($admin, 'admin')
        ->post(route('admin.invoices.refund', $invoice), ['amount' => 90.0, 'reason' => 'test', 'gateway_refund' => 0])
        ->assertRedirect();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Refunded->value);
});
