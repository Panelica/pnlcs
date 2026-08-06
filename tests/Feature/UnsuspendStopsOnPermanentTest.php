<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ModuleQueue;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\Service;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Asking a panel the same impossible question every half hour.
 *
 * The queue learned to tell a server that did not answer from a service the
 * panel has no account for, and to stop retrying the second kind. The command
 * that runs every thirty minutes did not: it called the module again on every
 * run, whatever the queue had already concluded.
 *
 * Four services on this installation are in that state. Their queue entries
 * have been failed since yesterday evening, and the same refusal has still
 * been written to the log a hundred times a day since.
 */
function paidButUnprovisionable(): Service
{
    $client = Client::factory()->create();

    $server = Server::factory()->create([
        'type' => 'panelica', 'hostname' => 'panel.permanent.test',
        'access_hash' => 'sk', 'password' => 'pk', 'active' => true,
    ]);

    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'server_type' => 'panelica',
    ]);

    $service = Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'server_id' => $server->id,
        'status' => 'suspended',
        'suspension_date' => now()->subDays(5),
        'suspension_reason' => 'Overdue Invoice - Automatic Suspension',
        'domain' => 'paid-but-stuck.test',
        'notes' => null,
    ]);

    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'status' => 'Paid',
        'date_paid' => now()->subDay(),
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

    return $service;
}

it('stops asking the panel once the queue has given up for good', function () {
    Mail::fake();
    Http::fake(['*' => Http::response(['message' => 'not found'], 404)]);

    $service = paidButUnprovisionable();

    $this->artisan('pnlcs:unsuspend-on-payment')->assertSuccessful();

    $entry = ModuleQueue::where('service_id', $service->id)->where('action', 'unsuspend')->firstOrFail();
    expect($entry->status)->toBe('failed');

    // The module refuses this one without going near the panel, so the cost is
    // the work and the log line, not the traffic: the same refusal was written
    // every half hour, about a hundred times a day.
    $touchedAt = $entry->updated_at;

    Log::spy();

    $this->artisan('pnlcs:unsuspend-on-payment')->assertSuccessful();

    Log::shouldNotHaveReceived('warning');

    expect($entry->fresh()->updated_at->eq($touchedAt))->toBeTrue();
});

it('still tries again when the failure could come right', function () {
    Mail::fake();
    Http::fake(['*' => Http::response(['message' => 'gateway timeout'], 504)]);

    $service = paidButUnprovisionable();
    $service->update(['notes' => json_encode(['panelica_user_id' => 4242])]);

    $this->artisan('pnlcs:unsuspend-on-payment')->assertSuccessful();

    $before = count(Http::recorded(fn () => true));

    $this->artisan('pnlcs:unsuspend-on-payment')->assertSuccessful();

    expect(count(Http::recorded(fn () => true)))->toBeGreaterThan($before);
});

// A row the queue gave up on for a reason that could come right is picked back
// up, so the command must keep asking about those.
