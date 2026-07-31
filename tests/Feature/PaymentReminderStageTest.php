<?php

use App\Mail\PaymentReminderMail;
use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Support\Facades\Mail;

/**
 * Chasing an unpaid invoice.
 *
 * The reminders were sent by matching the due date to an exact day: seven days
 * before, three, one, then one, three and seven days after. A run that missed
 * a day — a deploy, a restart, a queue that was busy — skipped that reminder
 * for good, and nothing at all was sent once an invoice was more than a week
 * overdue. Thirty-seven invoices on this installation are past due, most of
 * them by months, and the last thing any of them heard was silence.
 */
function chaseInvoice(int $dueInDays, string $status = 'unpaid'): Invoice
{
    return Invoice::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'status' => $status,
        'total' => 75,
        'due_date' => now()->addDays($dueInDays)->toDateString(),
    ]);
}

test('an invoice coming due is chased once, not every day', function () {
    Mail::fake();
    chaseInvoice(7);

    $this->artisan('pnlcs:payment-reminders')->assertSuccessful();
    $this->artisan('pnlcs:payment-reminders')->assertSuccessful();

    Mail::assertQueuedCount(1);
});

test('a reminder the cron slept through still goes out', function () {
    Mail::fake();

    // Seven-day notice was due yesterday and nothing ran.
    chaseInvoice(6);

    $this->artisan('pnlcs:payment-reminders')->assertSuccessful();

    Mail::assertQueued(PaymentReminderMail::class);
});

test('an invoice long past due is chased once, not never', function () {
    Mail::fake();
    chaseInvoice(-45, 'overdue');

    $this->artisan('pnlcs:payment-reminders')->assertSuccessful();

    Mail::assertQueuedCount(1);

    $this->artisan('pnlcs:payment-reminders')->assertSuccessful();

    Mail::assertQueuedCount(1);
});

test('the stages follow each other as the date passes', function () {
    Mail::fake();
    $invoice = chaseInvoice(7);

    $this->artisan('pnlcs:payment-reminders')->assertSuccessful();
    expect($invoice->fresh()->reminder_stage)->toBe('due7');

    $invoice->update(['due_date' => now()->addDay()->toDateString()]);
    $this->artisan('pnlcs:payment-reminders')->assertSuccessful();
    expect($invoice->fresh()->reminder_stage)->toBe('due1');

    $invoice->update(['due_date' => now()->subDays(3)->toDateString(), 'status' => 'overdue']);
    $this->artisan('pnlcs:payment-reminders')->assertSuccessful();
    expect($invoice->fresh()->reminder_stage)->toBe('late3');

    Mail::assertQueuedCount(3);
});

test('a paid invoice is left in peace', function () {
    Mail::fake();
    chaseInvoice(-10, 'paid');

    $this->artisan('pnlcs:payment-reminders')->assertSuccessful();

    Mail::assertNothingQueued();
});
