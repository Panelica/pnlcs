<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Invoice;

/**
 * What the admin invoice screen says about an invoice that has been paid.
 *
 * The page lowercases the status into a variable on its first lines and then
 * compared the raw value against 'Paid' and 'Cancelled' four times over.
 * Invoices are stored lowercase, so a paid invoice never said it was paid,
 * never showed the date it was paid, and had its due date painted red as
 * though the money were still owed — twenty-six of them on this installation.
 */
function invoiceScreenAdmin(): Admin
{
    return Admin::factory()->create([
        'role_id' => AdminRole::factory()->create([
            'name' => 'Billing',
            'permissions' => ['list_invoices', 'view_invoices', 'manage_invoices'],
        ])->id,
    ]);
}

test('a paid invoice says when it was paid', function () {
    $invoice = Invoice::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'status' => 'paid',
        'total' => 40,
        'date_paid' => now()->subDays(3),
        'due_date' => now()->subDays(10),
    ]);

    $this->actingAs(invoiceScreenAdmin(), 'admin')
        ->get(route('admin.invoices.show', $invoice))
        ->assertOk()
        ->assertSee($invoice->date_paid->timezone(display_tz())->format(datetime_fmt()));
});

test('a paid invoice does not have its due date painted as overdue', function () {
    $invoice = Invoice::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'status' => 'paid',
        'total' => 40,
        'date_paid' => now()->subDays(3),
        'due_date' => now()->subDays(10),
    ]);

    $html = $this->actingAs(invoiceScreenAdmin(), 'admin')
        ->get(route('admin.invoices.show', $invoice))
        ->assertOk()
        ->getContent();

    $dueRow = substr($html, strpos($html, $invoice->due_date->format(date_fmt())) - 200, 260);

    expect($dueRow)->not->toContain('#d9534f');
});

test('a cancelled invoice says so', function () {
    $invoice = Invoice::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'status' => 'cancelled',
        'total' => 40,
    ]);

    $this->actingAs(invoiceScreenAdmin(), 'admin')
        ->get(route('admin.invoices.show', $invoice))
        ->assertOk()
        ->assertSee(__('admin.invoices.invoice_cancelled'));
});
