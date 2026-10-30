<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\TaxRule;
use App\Services\InvoiceService;

function editingAdmin(): Admin
{
    return Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);
}

// ---------------------------------------------------------------------------
// VAT breakdown groups by tax rule, not label
// ---------------------------------------------------------------------------

test('vat breakdown groups lines sharing a tax rule into one entry', function () {
    $rule = TaxRule::factory()->create(['name' => 'VAT 23%', 'tax_rate' => 23.00]);

    $client = Client::factory()->create(['tax_exempt' => false]);
    $invoice = Invoice::factory()->create(['client_id' => $client->id]);

    InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'client_id' => $client->id,
        'amount' => 100.00,
        'qty' => 1,
        'taxed' => true,
        'tax_rate' => 23.00,
        'tax_label' => 'VAT 23%',
        'tax_rule_id' => $rule->id,
    ]);

    InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'client_id' => $client->id,
        'amount' => 50.00,
        'qty' => 1,
        'taxed' => true,
        'tax_rate' => 23.00,
        'tax_label' => '23%',
        'tax_rule_id' => $rule->id,
    ]);

    $groups = $invoice->fresh()->vatBreakdown();

    expect($groups)->toHaveCount(1)
        ->and(array_key_first($groups))->toBe('VAT 23%')
        ->and($groups['VAT 23%']['rate'])->toBe(23.0)
        ->and($groups['VAT 23%']['amount'])->toBe(34.5);
});

test('vat breakdown keeps legacy lines grouped by label', function () {
    $client = Client::factory()->create(['tax_exempt' => false]);
    $invoice = Invoice::factory()->create(['client_id' => $client->id]);

    InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'client_id' => $client->id,
        'amount' => 100.00,
        'qty' => 1,
        'taxed' => true,
        'tax_rate' => 23.00,
        'tax_label' => 'Legacy 23%',
        'tax_rule_id' => null,
    ]);

    $groups = $invoice->fresh()->vatBreakdown();

    expect($groups)->toHaveCount(1)
        ->and(array_key_first($groups))->toBe('Legacy 23%');
});

test('vat breakdown sums two rules sharing a label instead of dropping one', function () {
    $ruleA = TaxRule::factory()->create(['name' => 'VAT 23%', 'tax_rate' => 23.00, 'country' => 'PL']);
    $ruleB = TaxRule::factory()->create(['name' => 'VAT 23%', 'tax_rate' => 23.00, 'country' => 'DE']);

    $client = Client::factory()->create(['tax_exempt' => false]);
    $invoice = Invoice::factory()->create(['client_id' => $client->id]);

    InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'client_id' => $client->id,
        'amount' => 100.00,
        'qty' => 1,
        'taxed' => true,
        'tax_rate' => 23.00,
        'tax_rule_id' => $ruleA->id,
    ]);
    InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'client_id' => $client->id,
        'amount' => 100.00,
        'qty' => 1,
        'taxed' => true,
        'tax_rate' => 23.00,
        'tax_rule_id' => $ruleB->id,
    ]);

    $groups = $invoice->fresh()->vatBreakdown();

    expect($groups)->toHaveCount(1)
        ->and(array_key_first($groups))->toBe('VAT 23%')
        ->and($groups['VAT 23%']['amount'])->toBe(46.0);
});

test('a line keeps its own label even when its rule was renamed', function () {
    $rule = TaxRule::factory()->create(['name' => 'PL VAT 23 (renamed)', 'tax_rate' => 23.00]);

    $client = Client::factory()->create(['tax_exempt' => false]);
    $invoice = Invoice::factory()->create(['client_id' => $client->id]);

    InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'client_id' => $client->id,
        'amount' => 100.00,
        'qty' => 1,
        'taxed' => true,
        'tax_rate' => 23.00,
        'tax_label' => 'VAT 23%',
        'tax_rule_id' => $rule->id,
    ]);

    $groups = $invoice->fresh()->vatBreakdown();

    expect($groups)->toHaveCount(1)
        ->and(array_key_first($groups))->toBe('VAT 23%');
});

// ---------------------------------------------------------------------------
// Line editing
// ---------------------------------------------------------------------------

test('rejects an unknown tax rule id when updating a line', function () {
    $client = Client::factory()->create();
    $invoice = Invoice::factory()->create(['client_id' => $client->id]);
    $item = InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'client_id' => $client->id,
        'amount' => 100.00,
        'qty' => 1,
        'taxed' => true,
        'tax_rate' => 23.00,
        'tax_label' => 'VAT 23%',
    ]);

    $this->actingAs(editingAdmin(), 'admin')
        ->put("/admin/invoices/{$invoice->id}/items/{$item->id}", [
            'description' => 'Changed',
            'qty' => 1,
            'amount' => 100.00,
            'tax_rule_id' => '999999',
        ])
        ->assertStatus(422);
});

test('updating a legacy line with __legacy__ keeps its tax', function () {
    $client = Client::factory()->create();
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'tax_rate' => 23.00]);
    $item = InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'client_id' => $client->id,
        'description' => 'Old',
        'amount' => 100.00,
        'qty' => 1,
        'taxed' => true,
        'tax_rate' => 23.00,
        'tax_label' => 'Legacy 23%',
        'tax_rule_id' => null,
    ]);

    $this->actingAs(editingAdmin(), 'admin')
        ->put("/admin/invoices/{$invoice->id}/items/{$item->id}", [
            'description' => 'Renamed',
            'qty' => 1,
            'amount' => 150.00,
            'tax_rule_id' => '__legacy__',
        ])
        ->assertRedirect();

    $item->refresh();

    expect($item->description)->toBe('Renamed')
        ->and((float) $item->amount)->toBe(150.0)
        ->and((float) $item->tax_rate)->toBe(23.0)
        ->and($item->tax_rule_id)->toBeNull();
});

test('updating a line to a tax rule applies its rate and label', function () {
    $rule = TaxRule::factory()->create(['name' => 'VAT 23%', 'tax_rate' => 23.00]);

    $client = Client::factory()->create();
    $invoice = Invoice::factory()->create(['client_id' => $client->id]);
    $item = InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'client_id' => $client->id,
        'amount' => 100.00,
        'qty' => 1,
        'taxed' => false,
        'tax_rate' => 0.0,
        'tax_label' => null,
        'tax_rule_id' => null,
    ]);

    $this->actingAs(editingAdmin(), 'admin')
        ->put("/admin/invoices/{$invoice->id}/items/{$item->id}", [
            'description' => 'Taxed',
            'qty' => 1,
            'amount' => 100.00,
            'tax_rule_id' => (string) $rule->id,
        ])
        ->assertRedirect();

    $item->refresh();

    expect($item->tax_rule_id)->toBe($rule->id)
        ->and((float) $item->tax_rate)->toBe(23.0)
        ->and($item->tax_label)->toBe('VAT 23%')
        ->and((bool) $item->taxed)->toBeTrue();
});

// ---------------------------------------------------------------------------
// Line deletion
// ---------------------------------------------------------------------------

test('deleting a line removes it and recalculates totals', function () {
    $client = Client::factory()->create(['tax_exempt' => false]);
    $invoice = Invoice::factory()->create(['client_id' => $client->id]);

    $itemA = InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'client_id' => $client->id,
        'amount' => 100.00,
        'qty' => 1,
        'taxed' => true,
        'tax_rate' => 23.00,
    ]);
    $itemB = InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'client_id' => $client->id,
        'amount' => 50.00,
        'qty' => 1,
        'taxed' => true,
        'tax_rate' => 23.00,
    ]);

    app(InvoiceService::class)->recalculateTotals($invoice);

    $this->actingAs(editingAdmin(), 'admin')
        ->delete("/admin/invoices/{$invoice->id}/items/{$itemB->id}")
        ->assertRedirect();

    expect(InvoiceItem::find($itemB->id))->toBeNull()
        ->and(InvoiceItem::find($itemA->id))->not->toBeNull();

    $invoice->refresh();

    expect((float) $invoice->subtotal)->toBe(100.0)
        ->and((float) $invoice->tax)->toBe(23.0)
        ->and((float) $invoice->total)->toBe(123.0);
});
