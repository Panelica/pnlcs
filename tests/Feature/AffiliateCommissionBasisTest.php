<?php

use App\Models\Affiliate;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\AffiliateService;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Mail;

/**
 * What an affiliate is actually paid a share of.
 *
 * Commission was taken from the invoice total of every paid invoice. Two
 * things are wrong with that.
 *
 * Adding funds is not a sale. A customer who put 100 on account earned the
 * affiliate a commission, and then earned another one when that same 100 was
 * used to pay for hosting: the business paid twice on one piece of revenue.
 *
 * And the total includes tax, which is the government's money, not the shop's.
 */
function referringPair(bool $onetime = false): array
{
    $affiliateOwner = Client::factory()->create(['email' => 'partner@example.test']);

    $affiliate = Affiliate::create([
        'client_id' => $affiliateOwner->id,
        'visitors' => 0,
        'pay_type' => 'percentage',
        'pay_amount' => 10,
        'onetime' => $onetime,
        'balance' => 0,
        'withdrawn' => 0,
    ]);

    $referred = Client::factory()->create([
        'affiliate_id' => $affiliate->id,
        'email' => 'referred@example.test',
    ]);

    return [$affiliate, $referred];
}

function payableInvoice(Client $client, string $itemType, float $amount, float $tax = 0): Invoice
{
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'status' => 'unpaid',
        'subtotal' => $amount,
        'tax' => $tax,
        'total' => $amount + $tax,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'client_id' => $client->id,
        'type' => $itemType,
        'description' => $itemType,
        'amount' => $amount,
        'taxed' => $tax > 0,
    ]);

    return $invoice->fresh('items');
}

test('adding funds earns nobody a commission', function () {
    Mail::fake();
    [$affiliate, $referred] = referringPair();

    $invoice = payableInvoice($referred, 'AddFunds', 100);
    app(PaymentService::class)->applyPayment($invoice, 'stripe', 'TXN-FUNDS', 100.0);

    expect((float) $affiliate->fresh()->balance)->toBe(0.0);
});

test('a sale still earns one', function () {
    Mail::fake();
    [$affiliate, $referred] = referringPair();

    $invoice = payableInvoice($referred, 'Hosting', 100);
    app(PaymentService::class)->applyPayment($invoice, 'stripe', 'TXN-SALE', 100.0);

    expect((float) $affiliate->fresh()->balance)->toBe(10.0);
});

test('the commission is not a share of the tax', function () {
    Mail::fake();
    [$affiliate, $referred] = referringPair();

    // 100 of hosting plus 20 of tax.
    $invoice = payableInvoice($referred, 'Hosting', 100, 20);
    app(PaymentService::class)->applyPayment($invoice, 'stripe', 'TXN-TAXED', 120.0);

    expect((float) $affiliate->fresh()->balance)->toBe(10.0);
});

test('money put on account and then spent is paid on once', function () {
    Mail::fake();
    [$affiliate, $referred] = referringPair();

    $funds = payableInvoice($referred, 'AddFunds', 100);
    app(PaymentService::class)->applyPayment($funds, 'stripe', 'TXN-F', 100.0);

    $hosting = payableInvoice($referred, 'Hosting', 100);
    app(PaymentService::class)->applyPayment($hosting, 'credit', 'TXN-H', 100.0);

    expect((float) $affiliate->fresh()->balance)->toBe(10.0);
});

test('two part refunds take back the whole commission', function () {
    Mail::fake();
    [$affiliate, $referred] = referringPair();

    $invoice = payableInvoice($referred, 'Hosting', 100);
    app(PaymentService::class)->applyPayment($invoice, 'stripe', 'TXN-PART', 100.0);

    expect((float) $affiliate->fresh()->balance)->toBe(10.0);

    // Half back, then the other half. The second reversal used to find the
    // first reversal as the latest commission row, read its zero as what had
    // been earned, and take nothing.
    app(AffiliateService::class)->reverseCommission($invoice->fresh(), 50.0);
    expect((float) $affiliate->fresh()->balance)->toBe(5.0);

    app(AffiliateService::class)->reverseCommission($invoice->fresh(), 50.0);
    expect((float) $affiliate->fresh()->balance)->toBe(0.0);
});

test('refunding more than was paid does not take back more than was earned', function () {
    Mail::fake();
    [$affiliate, $referred] = referringPair();

    $invoice = payableInvoice($referred, 'Hosting', 100);
    app(PaymentService::class)->applyPayment($invoice, 'stripe', 'TXN-OVER', 100.0);

    $affiliate->increment('balance', 40);

    app(AffiliateService::class)->reverseCommission($invoice->fresh(), 100.0);
    app(AffiliateService::class)->reverseCommission($invoice->fresh(), 100.0);

    // 50 on the books, 10 of it from this invoice.
    expect((float) $affiliate->fresh()->balance)->toBe(40.0);
});
