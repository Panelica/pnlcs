<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Service;
use Illuminate\Support\Facades\Mail;

/**
 * Turning a service back on while its invoice is still open.
 *
 * The unsuspend run asked only whether an unpaid or overdue invoice was left
 * against the service. Telling the shop "I have paid by bank transfer" moves
 * the invoice to payment_pending before any money arrives, and a part payment
 * leaves it partially_paid; neither counted. So the notice alone, together
 * with any invoice the customer had paid before, switched a service suspended
 * for non-payment back on at the next run.
 */
function serviceSuspendedOwing(string $openStatus): Service
{
    $client = Client::factory()->create();
    $service = Service::factory()->create([
        'client_id' => $client->id,
        'server_id' => null,
        'status' => 'suspended',
        'suspension_date' => now()->subDays(5),
        'suspension_reason' => 'Overdue Invoice - Automatic Suspension',
    ]);

    foreach (['paid' => now()->subMonth(), $openStatus => null] as $status => $paidAt) {
        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'status' => $status,
            'date_paid' => $paidAt,
            'due_date' => now()->subDays(10),
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

    return $service;
}

it('keeps a service off while its invoice is :dataset', function (string $openStatus) {
    Mail::fake();
    $service = serviceSuspendedOwing($openStatus);

    $this->artisan('pnlcs:unsuspend-on-payment')->assertSuccessful();

    expect(strtolower($service->fresh()->status))->toBe('suspended');
})->with(['payment_pending', 'partially_paid', 'collections', 'unpaid', 'overdue']);

it('turns the service back on once that invoice is paid', function () {
    Mail::fake();
    $service = serviceSuspendedOwing('payment_pending');
    Invoice::where('status', 'payment_pending')->update(['status' => 'paid', 'date_paid' => now()]);

    $this->artisan('pnlcs:unsuspend-on-payment')->assertSuccessful();

    expect(strtolower($service->fresh()->status))->toBe('active');
});

it('turns the service back on when the open invoice was cancelled', function () {
    Mail::fake();
    $service = serviceSuspendedOwing('payment_pending');
    Invoice::where('status', 'payment_pending')->update(['status' => 'cancelled']);

    $this->artisan('pnlcs:unsuspend-on-payment')->assertSuccessful();

    expect(strtolower($service->fresh()->status))->toBe('active');
});
