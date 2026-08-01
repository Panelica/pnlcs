<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Service;
use Illuminate\Support\Facades\Mail;

/**
 * Turning a service back on once the customer has paid.
 *
 * The command asked for an invoice paid in the last twenty-four hours. Miss a
 * day - the scheduler stopped, the queue jammed, an admin marked an old
 * invoice paid - and the service stayed off for good: nothing was owed, the
 * payment was on the books, and no run would ever look at it again. Two
 * services on this installation are sitting in exactly that state.
 *
 * It also never asked why the service was suspended, so one suspended for
 * fraud came back on as soon as an invoice was paid.
 */
function suspendedServiceFor(string $reason, ?string $paidAt, bool $stillOwes = false): Service
{
    $client = Client::factory()->create();

    $service = Service::factory()->create([
        'client_id' => $client->id,
        'server_id' => null,
        'status' => 'suspended',
        'suspension_date' => now()->subDays(10),
        'suspension_reason' => $reason,
        'domain' => 'suspended-example.com',
    ]);

    if ($paidAt !== null) {
        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'status' => 'Paid',
            'date_paid' => $paidAt,
            'total' => 50,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'client_id' => $client->id,
            'type' => 'Hosting',
            'rel_id' => $service->id,
            'description' => 'Hosting',
            'amount' => 50,
            'taxed' => false,
        ]);
    }

    if ($stillOwes) {
        $unpaid = Invoice::factory()->create([
            'client_id' => $client->id,
            'status' => 'Unpaid',
            'total' => 50,
        ]);

        InvoiceItem::create([
            'invoice_id' => $unpaid->id,
            'client_id' => $client->id,
            'type' => 'Hosting',
            'rel_id' => $service->id,
            'description' => 'Hosting',
            'amount' => 50,
            'taxed' => false,
        ]);
    }

    return $service;
}

test('a service paid for days ago is still turned back on', function () {
    Mail::fake();
    $service = suspendedServiceFor('Overdue Invoice - Automatic Suspension', now()->subDays(3));

    $this->artisan('pnlcs:unsuspend-on-payment')->assertSuccessful();

    expect(strtolower($service->fresh()->status))->toBe('active')
        ->and($service->fresh()->suspension_reason)->toBeNull();
});

test('a service paid for today is turned back on', function () {
    Mail::fake();
    $service = suspendedServiceFor('Overdue Invoice - Automatic Suspension', now());

    $this->artisan('pnlcs:unsuspend-on-payment')->assertSuccessful();

    expect(strtolower($service->fresh()->status))->toBe('active');
});

test('a service that still owes stays off', function () {
    Mail::fake();
    $service = suspendedServiceFor('Overdue Invoice - Automatic Suspension', now(), stillOwes: true);

    $this->artisan('pnlcs:unsuspend-on-payment')->assertSuccessful();

    expect(strtolower($service->fresh()->status))->toBe('suspended');
});

test('a service suspended for fraud is not turned back on by a payment', function () {
    Mail::fake();
    $service = suspendedServiceFor('Order marked as fraud', now());

    $this->artisan('pnlcs:unsuspend-on-payment')->assertSuccessful();

    expect(strtolower($service->fresh()->status))->toBe('suspended')
        ->and($service->fresh()->suspension_reason)->toBe('Order marked as fraud');
});

test('a service suspended by hand is left to the person who suspended it', function () {
    Mail::fake();
    $service = suspendedServiceFor('Abuse complaint', now());

    $this->artisan('pnlcs:unsuspend-on-payment')->assertSuccessful();

    expect(strtolower($service->fresh()->status))->toBe('suspended');
});
