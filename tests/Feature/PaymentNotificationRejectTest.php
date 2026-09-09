<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\PaymentNotification;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Mail;

/*
 * Filing a transfer notification parks the invoice in payment_pending; a
 * rejection has to put it back where it was. It used to put every rejected
 * invoice back to plain unpaid, which quietly cleared an overdue mark and
 * forgot a part payment already made.
 */

function notifiedInvoice(array $attrs): array
{
    $client = Client::factory()->create();
    $user = User::factory()->create();
    $user->clients()->attach($client->id);
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'total' => 100] + $attrs);

    return [$invoice, $user, $client];
}

function reviewer(): Admin
{
    return Admin::factory()->create([
        'role_id' => AdminRole::factory()->create(['name' => 'Billing', 'permissions' => ['manage_invoices']])->id,
    ]);
}

function fileAndReject($test, Invoice $invoice, User $user): void
{
    $test->actingAs($user)->post(route('client.invoices.payment-notification', $invoice), [
        'sender_name' => 'Payer', 'amount' => 100, 'transfer_date' => now()->toDateString(),
    ])->assertSessionHasNoErrors();
    expect($invoice->fresh()->status)->toBe('payment_pending');

    $notification = PaymentNotification::where('invoice_id', $invoice->id)->latest('id')->first();
    $test->actingAs(reviewer(), 'admin')
        ->post(route('admin.payment-notifications.reject', $notification), ['admin_note' => 'No such transfer'])
        ->assertSessionHasNoErrors();
}

test('rejecting a notification on an overdue invoice leaves it overdue', function () {
    Mail::fake();
    [$invoice, $user] = notifiedInvoice(['status' => 'overdue', 'due_date' => now()->subDays(10)]);

    fileAndReject($this, $invoice, $user);

    expect($invoice->fresh()->status)->toBe('overdue');
});

test('rejecting a notification on a part-paid invoice leaves it part paid', function () {
    Mail::fake();
    [$invoice, $user] = notifiedInvoice(['status' => 'unpaid', 'due_date' => now()->addDays(10)]);
    app(PaymentService::class)->applyPayment($invoice, 'banktransfer', 'tx-part', 30.0);
    expect($invoice->fresh()->status)->toBe('partially_paid');

    fileAndReject($this, $invoice->fresh(), $user);

    expect($invoice->fresh()->status)->toBe('partially_paid');
});

test('rejecting a notification on an invoice still within its due date leaves it unpaid', function () {
    Mail::fake();
    [$invoice, $user] = notifiedInvoice(['status' => 'unpaid', 'due_date' => now()->addDays(10)]);

    fileAndReject($this, $invoice, $user);

    expect($invoice->fresh()->status)->toBe('unpaid');
});
