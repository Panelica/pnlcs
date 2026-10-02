<?php

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Setting;
use App\Models\User;
use App\Services\AffiliateService;
use App\Services\InvoiceService;
use App\Services\MassPaymentService;
use App\Services\PaymentService;

/*
 * Paying several open invoices in one go.
 *
 * A customer with five open invoices had to pay five times. One payment
 * invoice now lists them; once paid, each is settled on its own path.
 */

function mpCustomer(float $credit = 0): array
{
    $user = User::factory()->create();
    $client = Client::factory()->create(['credit' => $credit]);
    $user->clients()->attach($client->id, ['owner' => true]);

    return [$user, $client];
}

function mpInvoice(Client $client, float $total, string $status = 'unpaid', string $itemType = 'Hosting'): Invoice
{
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id, 'status' => $status, 'type' => 'vat',
        'subtotal' => $total, 'total' => $total, 'credit' => 0, 'tax' => 0, 'due_date' => now()->addDays(3),
    ]);
    InvoiceItem::create(['invoice_id' => $invoice->id, 'client_id' => $client->id, 'type' => $itemType, 'description' => 'Item', 'amount' => $total]);

    return $invoice;
}

it('offers the open invoices to tick and pay together', function () {
    [$user, $client] = mpCustomer();
    $a = mpInvoice($client, 30);
    $b = mpInvoice($client, 20);
    mpInvoice($client, 10, 'paid');

    test()->actingAs($user)->get(route('client.invoices.index'))
        ->assertOk()
        ->assertSee(route('client.invoices.mass-pay'), false)
        ->assertSee('value="'.$a->id.'"', false)->assertSee('value="'.$b->id.'"', false);
});

it('raises one payment invoice, outside the VAT series, listing each balance', function () {
    [$user, $client] = mpCustomer();
    $a = mpInvoice($client, 30);
    $b = mpInvoice($client, 20);
    $nextVat = app(InvoiceService::class)->generateInvoiceNumber('vat');

    $response = test()->actingAs($user)->post(route('client.invoices.mass-pay'), ['invoice_ids' => [$a->id, $b->id]]);

    $mass = Invoice::where('type', MassPaymentService::TYPE)->sole();
    $response->assertRedirect(route('client.invoices.show', $mass));
    expect((float) $mass->total)->toBe(50.0)
        ->and((float) $mass->tax)->toBe(0.0)
        ->and($mass->invoice_num)->toStartWith('PAY-')
        ->and($mass->items()->where('type', 'Invoice')->pluck('rel_id')->sort()->values()->all())->toBe(collect([$a->id, $b->id])->sort()->values()->all())
        ->and(app(InvoiceService::class)->generateInvoiceNumber('vat'))->toBe($nextVat);
});

it('settles each invoice once the payment invoice is paid', function () {
    [$user, $client] = mpCustomer();
    $a = mpInvoice($client, 30);
    $b = mpInvoice($client, 20);
    test()->actingAs($user)->post(route('client.invoices.mass-pay'), ['invoice_ids' => [$a->id, $b->id]]);
    $mass = Invoice::where('type', MassPaymentService::TYPE)->sole();

    app(PaymentService::class)->applyPayment($mass, 'banktransfer', 'tx-mass-1', null);

    expect($mass->fresh()->status)->toBe(InvoiceStatus::Paid->value)
        ->and($a->fresh()->status)->toBe(InvoiceStatus::Paid->value)
        ->and($b->fresh()->status)->toBe(InvoiceStatus::Paid->value)
        ->and((float) $client->fresh()->credit)->toBe(0.0);
});

it('keeps on the account what an invoice paid elsewhere meanwhile no longer needs', function () {
    [$user, $client] = mpCustomer();
    $a = mpInvoice($client, 30);
    $b = mpInvoice($client, 20);
    test()->actingAs($user)->post(route('client.invoices.mass-pay'), ['invoice_ids' => [$a->id, $b->id]]);
    $mass = Invoice::where('type', MassPaymentService::TYPE)->sole();

    app(PaymentService::class)->applyPayment($b, 'banktransfer', 'tx-b-alone', null);
    app(PaymentService::class)->applyPayment($mass, 'banktransfer', 'tx-mass-2', null);

    expect($a->fresh()->status)->toBe(InvoiceStatus::Paid->value)
        ->and((float) $client->fresh()->credit)->toBe(20.0);
});

it('takes only the customer\'s own open invoices, and needs two', function () {
    [$user, $client] = mpCustomer();
    [, $other] = mpCustomer();
    $mine = mpInvoice($client, 30);
    $funds = mpInvoice($client, 50, 'unpaid', 'AddFunds');
    $theirs = mpInvoice($other, 40);

    test()->actingAs($user)->post(route('client.invoices.mass-pay'), ['invoice_ids' => [$mine->id, $funds->id, $theirs->id]])
        ->assertSessionHas('error', __('client.invoices.mass_pay_pick_two'));

    expect(Invoice::where('type', MassPaymentService::TYPE)->count())->toBe(0);
});

it('withdraws an older unpaid payment invoice when a new one is raised', function () {
    [$user, $client] = mpCustomer();
    $a = mpInvoice($client, 30);
    $b = mpInvoice($client, 20);
    $c = mpInvoice($client, 10);

    test()->actingAs($user)->post(route('client.invoices.mass-pay'), ['invoice_ids' => [$a->id, $b->id]]);
    test()->actingAs($user)->post(route('client.invoices.mass-pay'), ['invoice_ids' => [$a->id, $b->id, $c->id]]);

    expect(Invoice::where('type', MassPaymentService::TYPE)->pluck('status')->sort()->values()->all())
        ->toBe([InvoiceStatus::Cancelled->value, InvoiceStatus::Unpaid->value]);
});

it('earns no commission, takes no late fee and is not paid from credit', function () {
    [$user, $client] = mpCustomer(500);
    $a = mpInvoice($client, 30);
    $b = mpInvoice($client, 20);
    test()->actingAs($user)->post(route('client.invoices.mass-pay'), ['invoice_ids' => [$a->id, $b->id]]);
    $mass = Invoice::where('type', MassPaymentService::TYPE)->sole();

    expect(app(AffiliateService::class)->commissionBase($mass))->toBe(0.0);

    test()->actingAs($user)->get(route('client.invoices.show', $mass))->assertOk()
        ->assertDontSee(route('client.invoices.pay-with-credit', $mass), false);

    Setting::set('LateFeeType', 'flat');
    Setting::set('LateFeeAmount', '5');
    Setting::set('LateFeeMinDays', '0');
    $mass->update(['status' => 'overdue', 'due_date' => now()->subDays(10)]);
    $a->update(['status' => 'overdue', 'due_date' => now()->subDays(10)]);
    test()->artisan('pnlcs:apply-late-fees');
    // The invoice itself takes its fee; the payment invoice for it does not.
    expect($a->items()->where('type', 'LateFee')->exists())->toBeTrue()
        ->and($mass->items()->where('type', 'LateFee')->exists())->toBeFalse();
});

it('issues VAT invoices for the proformas it pays, and none for itself', function () {
    // app/Hooks files are loaded with require_once, so only the first
    // application in a test process registers them; register the proforma
    // hook in this one so the test exercises the real path.
    require app_path('Hooks/proforma.php');
    Setting::set('ProformaEnabled', '1');
    [$user, $client] = mpCustomer();
    $a = mpInvoice($client, 30);
    $b = mpInvoice($client, 20);
    $a->update(['type' => 'proforma']);
    $b->update(['type' => 'proforma']);

    test()->actingAs($user)->post(route('client.invoices.mass-pay'), ['invoice_ids' => [$a->id, $b->id]]);
    $mass = Invoice::where('type', MassPaymentService::TYPE)->sole();
    app(PaymentService::class)->applyPayment($mass, 'banktransfer', 'tx-mass-pro', null);

    $vat = Invoice::where('client_id', $client->id)->where('type', 'vat')->get();
    expect($vat)->toHaveCount(2)
        ->and($vat->pluck('source_invoice_id')->sort()->values()->all())->toBe(collect([$a->id, $b->id])->sort()->values()->all());
});
