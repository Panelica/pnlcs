<?php

use App\Models\Client;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Mail;

/**
 * Money a customer has already handed over.
 *
 * Credit arrives from three places — the Add Funds page, an overpayment, and a
 * payment that lands on an invoice which is already settled — and every one of
 * them increments clients.credit. Spending it is the other half, and
 * InvoiceService::applyCredit had no callers anywhere in the codebase, so the
 * balance simply sat there while the customer kept being invoiced in full.
 */
function creditFixture(): array
{
    $currency = Currency::where('is_default', true)->first()
        ?? Currency::create(['code' => 'USD', 'prefix' => '$', 'suffix' => '', 'rate' => 1, 'is_default' => true]);

    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'tax' => false,
    ]);
    Pricing::updateOrCreate(
        ['type' => 'product', 'rel_id' => $product->id, 'currency_id' => $currency->id],
        ['monthly' => 30]
    );

    return compact('product', 'currency');
}

test('credit a customer has paid in is spent on their next invoice', function () {
    Mail::fake();
    $fx = creditFixture();
    $client = Client::factory()->create(['credit' => 50, 'tax_exempt' => true]);

    Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => $fx['product']->id,
        'status' => 'active',
        'amount' => 30,
        'billing_cycle' => 'Monthly',
        'next_due_date' => now()->addDays(3),
        'domain' => 'has-credit.com',
    ]);

    $this->artisan('pnlcs:generate-invoices')->assertSuccessful();

    $invoice = Invoice::where('client_id', $client->id)->latest('id')->firstOrFail();

    expect($invoice->status)->toBe('paid')
        ->and((float) $invoice->credit)->toBe(30.0)
        ->and((float) $client->fresh()->credit)->toBe(20.0);
});

test('credit that only covers part of the bill leaves the rest to pay', function () {
    Mail::fake();
    $fx = creditFixture();
    $client = Client::factory()->create(['credit' => 10, 'tax_exempt' => true]);

    Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => $fx['product']->id,
        'status' => 'active',
        'amount' => 30,
        'billing_cycle' => 'Monthly',
        'next_due_date' => now()->addDays(3),
        'domain' => 'part-credit.com',
    ]);

    $this->artisan('pnlcs:generate-invoices')->assertSuccessful();

    $invoice = Invoice::where('client_id', $client->id)->latest('id')->firstOrFail();

    expect((float) $invoice->total)->toBe(20.0)
        ->and($invoice->status)->not->toBe('paid')
        ->and((float) $client->fresh()->credit)->toBe(0.0);
});

test('an overpayment comes back as credit and is then usable', function () {
    Mail::fake();
    $fx = creditFixture();
    $client = Client::factory()->create(['credit' => 0, 'tax_exempt' => true]);

    $first = app(InvoiceService::class)->createInvoice($client, [
        ['type' => 'Hosting', 'rel_id' => 0, 'description' => 'First month', 'amount' => 30, 'taxed' => false],
    ]);

    // The customer pays 50 for a 30 invoice.
    app(PaymentService::class)->applyPayment($first, 'banktransfer', 'TXN-OVER', 50.0);

    expect((float) $client->fresh()->credit)->toBe(20.0);

    // The next invoice should draw on it rather than ask for the money again.
    $second = app(InvoiceService::class)->createInvoice($client->fresh(), [
        ['type' => 'Hosting', 'rel_id' => 0, 'description' => 'Second month', 'amount' => 30, 'taxed' => false],
    ]);

    expect((float) $second->fresh()->total)->toBe(10.0)
        ->and((float) $client->fresh()->credit)->toBe(0.0);
});

test('topping up does not immediately spend itself', function () {
    Mail::fake();
    $client = Client::factory()->create(['credit' => 40, 'tax_exempt' => true]);

    // An Add Funds invoice must not be settled out of the balance it is meant
    // to top up, or the customer's money goes round in a circle.
    $invoice = app(InvoiceService::class)->createInvoice($client, [
        ['type' => 'AddFunds', 'rel_id' => 0, 'description' => 'Add Funds', 'amount' => 25, 'taxed' => false],
    ]);

    expect((float) $invoice->fresh()->total)->toBe(25.0)
        ->and($invoice->fresh()->status)->toBe('unpaid')
        ->and((float) $client->fresh()->credit)->toBe(40.0);
});

test('refunding a top-up takes the credit back with it', function () {
    Mail::fake();
    $client = Client::factory()->create(['credit' => 0, 'tax_exempt' => true]);

    $invoice = app(InvoiceService::class)->createInvoice($client, [
        ['type' => 'AddFunds', 'rel_id' => 0, 'description' => 'Add Funds', 'amount' => 100, 'taxed' => false],
    ]);

    app(PaymentService::class)->applyPayment($invoice, 'banktransfer', 'TXN-TOPUP', 100.0);
    expect((float) $client->fresh()->credit)->toBe(100.0);

    // The money goes back to the customer, so the balance cannot stay behind.
    app(PaymentService::class)->refundInvoice($invoice->fresh(), 100.0);

    expect((float) $client->fresh()->credit)->toBe(0.0)
        ->and($invoice->fresh()->status)->toBe('refunded');
});

test('a partial refund of a top-up takes back only that part', function () {
    Mail::fake();
    $client = Client::factory()->create(['credit' => 0, 'tax_exempt' => true]);

    $invoice = app(InvoiceService::class)->createInvoice($client, [
        ['type' => 'AddFunds', 'rel_id' => 0, 'description' => 'Add Funds', 'amount' => 100, 'taxed' => false],
    ]);
    app(PaymentService::class)->applyPayment($invoice, 'banktransfer', 'TXN-PART', 100.0);

    app(PaymentService::class)->refundInvoice($invoice->fresh(), 40.0);

    expect((float) $client->fresh()->credit)->toBe(60.0);
});

test('a refund cannot pull back credit the customer has already spent', function () {
    Mail::fake();
    $fx = creditFixture();
    $client = Client::factory()->create(['credit' => 0, 'tax_exempt' => true]);

    $topUp = app(InvoiceService::class)->createInvoice($client, [
        ['type' => 'AddFunds', 'rel_id' => 0, 'description' => 'Add Funds', 'amount' => 100, 'taxed' => false],
    ]);
    app(PaymentService::class)->applyPayment($topUp, 'banktransfer', 'TXN-SPENT', 100.0);

    // They spend 30 of it on hosting.
    app(InvoiceService::class)->createInvoice($client->fresh(), [
        ['type' => 'Hosting', 'rel_id' => 0, 'description' => 'Hosting', 'amount' => 30, 'taxed' => false],
    ]);
    expect((float) $client->fresh()->credit)->toBe(70.0);

    // Refunding the full top-up can only reclaim what is left.
    app(PaymentService::class)->refundInvoice($topUp->fresh(), 100.0);

    expect((float) $client->fresh()->credit)->toBe(0.0);
});
