<?php

use App\Events\ServiceUnsuspended;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Service;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

/*
 * Activated, suspended and terminated each raised an event and a hook;
 * lifting a suspension raised nothing. An addon that switches something off
 * on ServiceSuspended (a licence, an outside account) never heard that the
 * customer had paid and the service was back.
 */

function suUnpaidThenPaid(): Service
{
    $client = Client::factory()->create();
    $service = Service::factory()->create([
        'client_id' => $client->id, 'server_id' => null, 'status' => 'suspended',
        'suspension_date' => now()->subDays(10), 'suspension_reason' => 'Overdue Invoice - Automatic Suspension', 'domain' => 'back-example.com',
    ]);
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'Paid', 'date_paid' => now()->subDay(), 'total' => 50]);
    InvoiceItem::create(['invoice_id' => $invoice->id, 'client_id' => $client->id, 'type' => 'Hosting', 'rel_id' => $service->id,
        'description' => 'Hosting', 'amount' => 50, 'taxed' => false]);

    return $service;
}

test('lifting an overdue suspension raises ServiceUnsuspended', function () {
    Mail::fake();
    $service = suUnpaidThenPaid();

    Event::fake([ServiceUnsuspended::class]);
    $this->artisan('pnlcs:unsuspend-on-payment')->assertSuccessful();

    expect($service->fresh()->status)->toBe('active');
    Event::assertDispatched(ServiceUnsuspended::class, fn ($e) => $e->service->id === $service->id);
});

test('the ServiceUnsuspended hook runs once, and the activity log says so', function () {
    Mail::fake();
    $service = suUnpaidThenPaid();
    $calls = [];
    add_hook('ServiceUnsuspended', function (array $vars) use (&$calls) {
        $calls[] = $vars;
    });

    $this->artisan('pnlcs:unsuspend-on-payment')->assertSuccessful();

    expect($calls)->toHaveCount(1)
        ->and($calls[0]['service']->id)->toBe($service->id)
        ->and(ActivityLog::where('client_id', $service->client_id)->where('description', "Service #{$service->id} unsuspended")->exists())->toBeTrue();
});
