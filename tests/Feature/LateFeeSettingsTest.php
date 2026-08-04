<?php

use App\Models\Admin;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Setting;

/**
 * Late fees.
 *
 * The command runs every morning and reads three settings - the kind of fee,
 * how much, and how many days late - that no screen could write. So it read
 * "none" every time, said late fees were disabled, and stopped. The feature
 * was finished on the reading side and unreachable from the panel.
 */
function overdueInvoiceForLateFee(float $total = 100.0, int $daysLate = 10): Invoice
{
    $client = Client::factory()->create();

    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'status' => 'overdue',
        'due_date' => now()->subDays($daysLate),
        'subtotal' => $total,
        'total' => $total,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'client_id' => $client->id,
        'type' => 'Hosting',
        'description' => 'Hosting',
        'amount' => $total,
        'taxed' => false,
    ]);

    return $invoice;
}

it('lets the operator set a late fee', function () {
    $this->actingAs(Admin::factory()->create(), 'admin')
        ->post(route('admin.settings.general.update'), [
            'CompanyName' => 'Test Co',
            'LateFeeType' => 'percent',
            'LateFeeAmount' => '5',
            'LateFeeMinDays' => '7',
        ])->assertRedirect();

    expect(Setting::get('LateFeeType'))->toBe('percent')
        ->and((float) Setting::get('LateFeeAmount'))->toBe(5.0)
        ->and((int) Setting::get('LateFeeMinDays'))->toBe(7);
});

it('offers the late fee fields on the settings screen', function () {
    $html = $this->actingAs(Admin::factory()->create(), 'admin')
        ->get(route('admin.settings.general'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('name="LateFeeType"')
        ->and($html)->toContain('name="LateFeeAmount"')
        ->and($html)->toContain('name="LateFeeMinDays"');
});

it('charges the fee once the operator has set one', function () {
    Setting::set('LateFeeType', 'percent', 'general');
    Setting::set('LateFeeAmount', '5', 'general');
    Setting::set('LateFeeMinDays', '7', 'general');

    $invoice = overdueInvoiceForLateFee(100.0, 10);

    $this->artisan('pnlcs:apply-late-fees')->assertSuccessful();

    $invoice->refresh();

    expect($invoice->items()->where('type', 'LateFee')->count())->toBe(1)
        ->and(round((float) $invoice->total, 2))->toBe(105.00);

    // A second morning does not charge it again.
    $this->artisan('pnlcs:apply-late-fees')->assertSuccessful();

    expect($invoice->fresh()->items()->where('type', 'LateFee')->count())->toBe(1);
});
