<?php

use App\Models\CancellationRequest;
use App\Models\Client;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\Service;
use Illuminate\Support\Facades\Http;

/**
 * A cancellation request records whether the customer wants to stop now or at
 * the end of the period they have paid for. Nothing read that field: every
 * request waited for next_due_date, so "Immediate" behaved exactly like "End of
 * Billing Period" and the choice on the form meant nothing.
 *
 * The two writers also disagree on wording — the customer form posts
 * "Immediate" / "End of Billing Period", the API defaults to "end_of_billing" —
 * so the check normalises before comparing.
 */
function cancellableService(): Service
{
    $server = Server::factory()->create(['type' => 'panelica', 'active' => true]);

    return Service::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'product_id' => Product::factory()->create([
            'group_id' => ProductGroup::factory()->create()->id,
            'server_type' => 'panelica',
        ])->id,
        'server_id' => $server->id,
        'status' => 'active',
        // Paid up for another three weeks.
        'next_due_date' => now()->addWeeks(3),
        'module_data' => ['panelica_user_id' => 'remote-1'],
    ]);
}

test('an immediate request is processed on the next run', function () {
    Http::fake(['*' => Http::response(['success' => true], 200)]);
    $service = cancellableService();
    CancellationRequest::create(['service_id' => $service->id, 'type' => 'Immediate', 'reason' => 'x']);

    $this->artisan('pnlcs:process-cancellations')->assertSuccessful();

    expect($service->fresh()->status)->toBe('cancelled')
        ->and($service->fresh()->termination_date)->not->toBeNull();
});

test('the API wording for an immediate request is understood too', function () {
    Http::fake(['*' => Http::response(['success' => true], 200)]);
    $service = cancellableService();
    CancellationRequest::create(['service_id' => $service->id, 'type' => 'immediate', 'reason' => 'x']);

    $this->artisan('pnlcs:process-cancellations')->assertSuccessful();

    expect($service->fresh()->status)->toBe('cancelled');
});

test('an end of period request waits for the period it was paid for', function () {
    Http::fake(['*' => Http::response(['success' => true], 200)]);
    $service = cancellableService();
    CancellationRequest::create(['service_id' => $service->id, 'type' => 'End of Billing Period', 'reason' => 'x']);

    $this->artisan('pnlcs:process-cancellations')->assertSuccessful();

    // Still three weeks of paid service left.
    expect($service->fresh()->status)->toBe('active');

    $service->update(['next_due_date' => now()->subDay()]);
    $this->artisan('pnlcs:process-cancellations')->assertSuccessful();

    expect($service->fresh()->status)->toBe('cancelled');
});

test('a service without a request is left alone', function () {
    Http::fake();
    $service = cancellableService();
    $service->update(['next_due_date' => now()->subDay()]);

    $this->artisan('pnlcs:process-cancellations')->assertSuccessful();

    expect($service->fresh()->status)->toBe('active');
    Http::assertNothingSent();
});
