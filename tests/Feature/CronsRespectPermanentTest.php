<?php

use App\Models\CancellationRequest;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ModuleQueue;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\Service;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * The nightly jobs and what the queue has already decided.
 *
 * The retry queue tells a server that did not answer from a service the panel
 * has no account for, and stops retrying the second kind. Switching a service
 * back on after payment now asks it. Auto-suspend and the cancellation run did
 * not: they called the module again on every run, whatever the queue had
 * concluded, and wrote the same refusal to the log each time.
 */
function stuckOnPanel(string $status = 'active'): Service
{
    $client = Client::factory()->create();

    $server = Server::factory()->create([
        'type' => 'panelica', 'hostname' => 'panel.stuck.test',
        'access_hash' => 'sk', 'password' => 'pk', 'active' => true,
    ]);

    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'server_type' => 'panelica',
    ]);

    return Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'server_id' => $server->id,
        'status' => $status,
        'domain' => 'stuck-on-panel.test',
        'next_due_date' => now()->subDays(20),
        'override_auto_suspend_date' => null,
        'notes' => null,
    ]);
}

function overdueFor(Service $service): void
{
    $invoice = Invoice::factory()->create([
        'client_id' => $service->client_id,
        'status' => 'overdue',
        'due_date' => now()->subDays(10),
        'total' => 25,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'client_id' => $service->client_id,
        'type' => 'Hosting',
        'rel_id' => $service->id,
        'description' => 'Hosting',
        'amount' => 25,
        'taxed' => false,
    ]);
}

it('does not keep auto-suspending something the queue gave up on', function () {
    Mail::fake();

    $service = stuckOnPanel();
    overdueFor($service);

    $this->artisan('pnlcs:auto-suspend')->assertSuccessful();

    $entry = ModuleQueue::where('service_id', $service->id)->where('action', 'suspend')->firstOrFail();
    expect($entry->status)->toBe('failed');

    $touchedAt = $entry->updated_at;

    Log::spy();

    $this->artisan('pnlcs:auto-suspend')->assertSuccessful();

    Log::shouldNotHaveReceived('warning');

    expect($entry->fresh()->updated_at->eq($touchedAt))->toBeTrue();
});

it('does not keep terminating something the queue gave up on', function () {
    Mail::fake();

    $service = stuckOnPanel();

    CancellationRequest::create([
        'service_id' => $service->id,
        'type' => 'immediate',
        'reason' => 'done with it',
    ]);

    $this->artisan('pnlcs:process-cancellations')->assertSuccessful();

    $entry = ModuleQueue::where('service_id', $service->id)->where('action', 'terminate')->firstOrFail();
    expect($entry->status)->toBe('failed');

    $touchedAt = $entry->updated_at;

    Log::spy();

    $this->artisan('pnlcs:process-cancellations')->assertSuccessful();

    Log::shouldNotHaveReceived('warning');

    expect($entry->fresh()->updated_at->eq($touchedAt))->toBeTrue();
});
