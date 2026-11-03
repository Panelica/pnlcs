<?php

use App\Enums\InvoiceStatus;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\TaxRule;
use App\Services\InvoiceService;
use App\Services\ProformaService;
use Illuminate\Support\Facades\Mail;

/**
 * The invoice builder offers the customer's tax rule whether or not the customer
 * is exempt. The exemption is frozen on the invoice as buyer_tax_exempt, and a
 * line written to such an invoice carries no tax.
 */
function ratedInvoice(bool $exempt, array $line = ['tax_rate' => 20, 'tax_label' => 'VAT 20'])
{
    Mail::fake();

    return app(InvoiceService::class)->createInvoice(Client::factory()->create(['tax_exempt' => $exempt]), [
        ['type' => 'Other', 'description' => 'Work', 'amount' => 100, ...$line],
    ]);
}

test('a tax-exempt buyer pays no tax on a line carrying a rate', function () {
    $invoice = ratedInvoice(true);

    expect((float) $invoice->tax)->toEqual(0.0)
        ->and((float) $invoice->total)->toEqual(100.0)
        ->and((float) $invoice->tax_rate)->toEqual(0.0)
        ->and($invoice->lineTax($invoice->items->first())['vat'])->toEqual(0.0)
        ->and($invoice->vatBreakdown())->toBe([]);
});

test('a tax-exempt buyer pays no tax on a line linked to a tax rule', function () {
    $rule = TaxRule::factory()->create(['name' => 'VAT 20', 'tax_rate' => 20]);

    $invoice = ratedInvoice(true, ['tax_rule_id' => $rule->id]);

    expect((float) $invoice->tax)->toEqual(0.0)
        ->and((float) $invoice->total)->toEqual(100.0)
        ->and($invoice->items->first()->tax_rule_id)->toBeNull();
});

test('a buyer who is not exempt still pays the rate on the line', function () {
    $invoice = ratedInvoice(false);

    expect((float) $invoice->tax)->toEqual(20.0)
        ->and((float) $invoice->total)->toEqual(120.0)
        ->and((float) $invoice->tax_rate)->toEqual(20.0);
});

test('choosing a tax rule when editing a line of an exempt buyer adds no tax', function () {
    $invoice = ratedInvoice(true);
    $rule = TaxRule::factory()->create(['name' => 'VAT 20', 'tax_rate' => 20]);
    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);

    $this->actingAs($admin, 'admin')
        ->put(route('admin.invoices.items.update', [$invoice, $invoice->items->first()]), [
            'description' => 'Work',
            'qty' => 1,
            'amount' => 100,
            'tax_rule_id' => (string) $rule->id,
        ])
        ->assertRedirect();

    expect((float) $invoice->fresh()->tax)->toEqual(0.0)
        ->and((float) $invoice->fresh()->total)->toEqual(100.0);
});

test('a zero-rate rule keeps its label and its VAT row for an exempt buyer', function () {
    $rule = TaxRule::factory()->create(['name' => 'np.', 'tax_rate' => 0]);
    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);

    $invoice = ratedInvoice(true, ['tax_rule_id' => $rule->id]);

    $this->actingAs($admin, 'admin')
        ->put(route('admin.invoices.items.update', [$invoice, $invoice->items->first()]), [
            'description' => 'Work edited',
            'qty' => 1,
            'amount' => 100,
            'tax_rule_id' => (string) $rule->id,
        ])
        ->assertRedirect();

    $invoice = $invoice->fresh();
    $line = $invoice->items->first();

    expect($line->tax_rule_id)->toBe($rule->id)
        ->and($line->tax_label)->toBe('np.')
        ->and((float) $invoice->total)->toEqual(100.0)
        ->and($invoice->vatBreakdown()['np.']['net'])->toEqual(100.0)
        ->and($invoice->vatBreakdown()['np.']['amount'])->toEqual(0.0);
});

test('with prices that include tax, an exempt buyer pays the same total with no VAT in it', function () {
    Mail::fake();

    $invoice = app(InvoiceService::class)->createInvoice(Client::factory()->create(['tax_exempt' => true]), [
        ['type' => 'Other', 'description' => 'Work', 'amount' => 120, 'tax_rate' => 20, 'tax_label' => 'VAT 20'],
    ], ['tax_mode' => Invoice::TAX_INCLUSIVE]);

    expect((float) $invoice->tax)->toEqual(0.0)
        ->and((float) $invoice->subtotal)->toEqual(120.0)
        ->and((float) $invoice->total)->toEqual(120.0)
        ->and($invoice->vatBreakdown())->toBe([]);
});

test('the exemption frozen on the invoice holds when the customer record changes later', function () {
    Mail::fake();

    $client = Client::factory()->create(['tax_exempt' => true, 'country' => 'GB', 'state' => '']);
    TaxRule::create(['name' => 'VAT', 'country' => 'GB', 'state' => '', 'tax_rate' => 20]);

    $invoice = app(InvoiceService::class)->createInvoice($client, [
        ['type' => 'Hosting', 'description' => 'Hosting', 'amount' => 100, 'taxed' => true],
    ]);

    $client->update(['tax_exempt' => false]);

    $updated = app(InvoiceService::class)->recalculateTotals($invoice->fresh());

    expect((float) $updated->tax)->toEqual(0.0)
        ->and((float) $updated->total)->toEqual(100.0);
});

test('a document already issued to an exempt buyer with tax keeps what it printed', function () {
    $client = Client::factory()->create(['tax_exempt' => true]);
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'buyer_tax_exempt' => true,
        'subtotal' => 100,
        'tax' => 20,
        'total' => 120,
    ]);
    InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'client_id' => $client->id,
        'amount' => 100,
        'qty' => 1,
        'taxed' => true,
        'tax_rate' => 20,
        'tax_label' => 'VAT 20',
    ]);

    $invoice = $invoice->fresh();

    expect($invoice->lineTax($invoice->items->first())['vat'])->toEqual(20.0)
        ->and($invoice->vatBreakdown()['VAT 20']['amount'])->toEqual(20.0)
        ->and((float) app(InvoiceService::class)->recalculateTotals($invoice)->total)->toEqual(120.0);
});

test('a paid proforma keeps its tax on the VAT invoice when the buyer became exempt meanwhile', function () {
    Mail::fake();

    $client = Client::factory()->create(['tax_exempt' => false]);
    $proforma = app(InvoiceService::class)->createInvoice($client, [
        ['type' => 'Other', 'description' => 'Work', 'amount' => 100, 'tax_rate' => 20, 'tax_label' => 'VAT 20'],
    ], ['type' => 'proforma']);
    $proforma->update(['status' => InvoiceStatus::Paid->value]);

    $client->update(['tax_exempt' => true]);

    $vat = app(ProformaService::class)->issueVatInvoice($proforma->fresh());

    expect((float) $vat->total)->toEqual(120.0)
        ->and($vat->fresh()->vatBreakdown()['VAT 20']['amount'])->toEqual(20.0);
});

test('editing one line of a legacy invoice does not leave an exempt buyer half taxed', function () {
    $client = Client::factory()->create(['tax_exempt' => true]);
    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);

    // A legacy invoice: the tax sits on the invoice, not on its lines.
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id, 'buyer_tax_exempt' => true,
        'tax_rate' => 20, 'subtotal' => 200, 'tax' => 40, 'total' => 240,
        'status' => InvoiceStatus::Unpaid->value,
    ]);
    foreach (['One', 'Two'] as $description) {
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id, 'client_id' => $client->id,
            'description' => $description, 'amount' => 100, 'qty' => 1,
            'taxed' => true, 'tax_rate' => null, 'tax_label' => null, 'tax_rule_id' => null,
        ]);
    }

    $this->actingAs($admin, 'admin')
        ->put(route('admin.invoices.items.update', [$invoice, $invoice->items->first()]), [
            'description' => 'One edited', 'qty' => 1, 'amount' => 100, 'tax_rule_id' => '',
        ])
        ->assertRedirect();

    // Editing a line gives every line an explicit rate, which makes the
    // invoice a per-line one - and that is the path that does not read the
    // exemption. The line that was edited went to zero and the other kept the
    // invoice's 20%, on a document marked exempt.
    $invoice = $invoice->fresh();

    expect($invoice->items->map(fn ($item) => (float) $item->tax_rate)->all())->toBe([0.0, 0.0])
        ->and((float) $invoice->tax)->toEqual(0.0)
        ->and((float) $invoice->total)->toEqual(200.0);
});
