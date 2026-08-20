<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\TaxRule;

/**
 * The "taxed" box on a manual invoice line.
 *
 * An unticked checkbox is not submitted at all - the browser leaves it out -
 * and the controller read a missing value as taxed. A line the admin had
 * deliberately marked as not taxable was therefore taxed anyway, and the
 * customer was billed for it.
 */
function taxingAdmin(): Admin
{
    return Admin::factory()->create([
        'role_id' => AdminRole::factory()->fullAdmin()->create()->id,
    ]);
}

function taxedClient(): Client
{
    TaxRule::create([
        'level' => 1,
        'name' => 'VAT',
        'country' => 'TR',
        'state' => '',
        'tax_rate' => 20,
    ]);

    return Client::factory()->create(['country' => 'TR', 'state' => '', 'tax_exempt' => false]);
}

test('a line the admin did not tick is not taxed', function () {
    $client = taxedClient();

    $this->actingAs(taxingAdmin(), 'admin')
        ->post(route('admin.invoices.store'), [
            'client_id' => $client->id,
            'date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            // How a browser posts a form whose only checkbox is unticked.
            'items' => [
                ['description' => 'Refund of a deposit', 'amount' => 100],
            ],
        ])->assertRedirect();

    $invoice = Invoice::latest('id')->firstOrFail();

    expect((bool) $invoice->items->first()->taxed)->toBeFalse()
        ->and((float) $invoice->tax)->toBe(0.0)
        ->and((float) $invoice->total)->toBe(100.0);
});

test('a line the admin did tick is taxed', function () {
    $client = taxedClient();

    $this->actingAs(taxingAdmin(), 'admin')
        ->post(route('admin.invoices.store'), [
            'client_id' => $client->id,
            'date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'items' => [
                // The checkbox became a per-line rate; the guarantee is the same.
                ['description' => 'Hosting', 'amount' => 100, 'tax_rate' => 20],
            ],
        ])->assertRedirect();

    $invoice = Invoice::latest('id')->firstOrFail();

    expect((bool) $invoice->items->first()->taxed)->toBeTrue()
        ->and((float) $invoice->tax)->toBe(20.0)
        ->and((float) $invoice->total)->toBe(120.0);
});

test('every line carries its own rate field, so the server is never left guessing', function () {
    $markup = file_get_contents(resource_path('views/admin/invoices/create.blade.php'));

    // The taxed checkbox was replaced by a per-line rate. A checkbox alone told
    // the server nothing when it was unticked, which is the bug this file was
    // written for; a rate field always posts a value, so the same guarantee
    // holds by construction.
    expect($markup)->toContain('items[${index}][tax_rate]');
});
