<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Service;
use Illuminate\Support\Facades\Mail;

/**
 * The two halves of the billing suspension disagreeing with each other.
 *
 * Auto-suspend asks a question about the client: does this client have an
 * invoice that has been overdue longer than the grace period? If so, every
 * active service they have goes off - the invoice does not have to be for the
 * service being suspended, and on this installation it often is not: a domain
 * renewal, a one-off charge, an addon.
 *
 * Unsuspend-on-payment asks a question about the service: has an invoice
 * carrying this service been paid, and is nothing carrying this service still
 * outstanding? An old paid hosting invoice satisfies the first, and a debt that
 * is not tied to the service satisfies the second.
 *
 * So a client behind on a domain renewal had their hosting suspended at 07:00
 * and switched back on by 07:30 - with a suspension email and an unsuspension
 * email each time - and again the next morning, for as long as the debt stood.
 */
function behindClientService(bool $debtStillOpen, int $overdueDays = 10): Service
{
    $client = Client::factory()->create();

    $service = Service::factory()->create([
        'client_id' => $client->id,
        'server_id' => null,
        'status' => 'suspended',
        'suspension_date' => now()->subDay(),
        'suspension_reason' => 'Overdue Invoice - Automatic Suspension',
        'domain' => 'flapping-example.com',
    ]);

    // The hosting invoice for this service: paid, months ago.
    $paid = Invoice::factory()->create([
        'client_id' => $client->id,
        'status' => 'Paid',
        'date_paid' => now()->subMonths(2),
        'total' => 50,
    ]);

    InvoiceItem::create([
        'invoice_id' => $paid->id,
        'client_id' => $client->id,
        'type' => 'Hosting',
        'rel_id' => $service->id,
        'description' => 'Hosting',
        'amount' => 50,
        'taxed' => false,
    ]);

    // The debt that got them suspended: a domain renewal, nothing to do with
    // the service.
    $domainInvoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'status' => $debtStillOpen ? 'Overdue' : 'Paid',
        'due_date' => now()->subDays($overdueDays),
        'date_paid' => $debtStillOpen ? null : now(),
        'total' => 15,
    ]);

    InvoiceItem::create([
        'invoice_id' => $domainInvoice->id,
        'client_id' => $client->id,
        'type' => 'Domain',
        'rel_id' => 0,
        'description' => 'Domain renewal',
        'amount' => 15,
        'taxed' => false,
    ]);

    return $service;
}

it('does not switch a service back on while the client is still behind', function () {
    Mail::fake();

    $service = behindClientService(debtStillOpen: true);

    $this->artisan('pnlcs:unsuspend-on-payment')->assertSuccessful();

    expect($service->fresh()->status)->toBe('suspended');

    Mail::assertNothingQueued();
});

it('switches it back on once the client owes nothing', function () {
    Mail::fake();

    $service = behindClientService(debtStillOpen: false);

    $this->artisan('pnlcs:unsuspend-on-payment')->assertSuccessful();

    expect($service->fresh()->status)->toBe('active')
        ->and($service->fresh()->suspension_reason)->toBeNull();
});

it('does not hold a service back for a debt too young to have suspended it', function () {
    Mail::fake();

    // Overdue since yesterday: inside the grace period, so auto-suspend would
    // not act on it either.
    $service = behindClientService(debtStillOpen: true, overdueDays: 1);

    $this->artisan('pnlcs:unsuspend-on-payment')->assertSuccessful();

    expect($service->fresh()->status)->toBe('active');
});

it('still leaves a service suspended by hand alone', function () {
    Mail::fake();

    $service = behindClientService(debtStillOpen: false);
    $service->update(['status' => 'suspended', 'suspension_reason' => 'Fraud investigation']);

    $this->artisan('pnlcs:unsuspend-on-payment')->assertSuccessful();

    expect($service->fresh()->status)->toBe('suspended');
});
