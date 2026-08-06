<?php

use App\Models\ApiCredential;
use App\Models\CancellationRequest;
use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use Database\Factories\ApiCredentialFactory;
use Illuminate\Support\Facades\Mail;

/**
 * What happens to a cancellation request once it has been acted on.
 *
 * Nothing did. The row stayed where it was for good, so:
 *
 *  - a service put back to active was cancelled again on the next nightly run,
 *    because the request was still sitting there;
 *  - the customer could never ask again, since the form refuses when any
 *    request exists;
 *  - a request against a suspended service was never processed at all - the
 *    command only looked at active ones - and this installation has one from
 *    April still waiting.
 *
 * The API also created requests without the duplicate check the form has, and
 * one service here carries two.
 */
function leavingService(string $status = 'active'): Service
{
    $client = Client::factory()->create();

    return Service::factory()->create([
        'client_id' => $client->id,
        'server_id' => null,
        'status' => $status,
        'domain' => 'leaving-example.com',
        'next_due_date' => now()->subDay(),
    ]);
}

test('a request is closed once it has been acted on', function () {
    Mail::fake();
    $service = leavingService();
    $request = CancellationRequest::create(['service_id' => $service->id, 'type' => 'immediate', 'reason' => 'done']);

    $this->artisan('pnlcs:process-cancellations')->assertSuccessful();

    expect(strtolower($service->fresh()->status))->toBe('cancelled')
        ->and($request->fresh()->processed_at)->not->toBeNull();
});

test('a service put back to work is not cancelled again', function () {
    Mail::fake();
    $service = leavingService();
    CancellationRequest::create(['service_id' => $service->id, 'type' => 'immediate', 'reason' => 'done']);

    $this->artisan('pnlcs:process-cancellations')->assertSuccessful();

    // An admin reinstates it - the customer changed their mind.
    Service::whereKey($service->id)->update(['status' => 'active', 'termination_date' => null]);

    $this->artisan('pnlcs:process-cancellations')->assertSuccessful();

    expect(strtolower($service->fresh()->status))->toBe('active');
});

test('a suspended service is let go when the customer asks', function () {
    Mail::fake();
    $service = leavingService('suspended');
    CancellationRequest::create(['service_id' => $service->id, 'type' => 'immediate', 'reason' => 'enough']);

    $this->artisan('pnlcs:process-cancellations')->assertSuccessful();

    expect(strtolower($service->fresh()->status))->toBe('cancelled');
});

test('the customer can ask again once the last request is closed', function () {
    Mail::fake();
    $service = leavingService();
    $user = User::factory()->create();
    $user->clients()->attach($service->client_id);

    CancellationRequest::create([
        'service_id' => $service->id,
        'type' => 'immediate',
        'reason' => 'first time',
        'processed_at' => now()->subMonth(),
    ]);

    Service::whereKey($service->id)->update(['status' => 'active']);

    $this->actingAs($user)
        ->post(route('client.services.cancel.submit', $service), [
            'type' => 'Immediate',
            'reason' => 'Changed my mind again, thank you',
        ])->assertRedirect();

    expect(CancellationRequest::where('service_id', $service->id)->whereNull('processed_at')->count())->toBe(1);
});

test('the api refuses a second open request', function () {
    $service = leavingService();
    CancellationRequest::create(['service_id' => $service->id, 'type' => 'immediate', 'reason' => 'first']);

    $credential = ApiCredential::factory()->create();

    $this->postJson('/api/v1/addcancelrequest', [
        'api_key' => $credential->identifier,
        'api_secret' => ApiCredentialFactory::PLAINTEXT_SECRET,
        'serviceid' => $service->id,
        'type' => 'immediate',
    ]);

    expect(CancellationRequest::where('service_id', $service->id)->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// A service that has asked to leave twice
// ---------------------------------------------------------------------------

/**
 * The second request is the one that counts.
 *
 * A service can carry more than one cancellation request over its life: the
 * form allows a new one once the previous has been acted on, the API never
 * checked at all, and one service on this installation already carries two.
 *
 * The relation is a plain hasOne with no ordering, so it hands back whichever
 * row the database returns first - the oldest. The nightly job finds the
 * service by its open request, cancels it, and then stamps the wrong row: the
 * old one is marked processed a second time and the open one is left open. The
 * moment the service is put back to work it is cancelled again, which is the
 * exact thing closing the request was meant to prevent.
 */
test('the request that is still open is the one that gets closed', function () {
    Mail::fake();
    $service = leavingService();

    $old = CancellationRequest::create([
        'service_id' => $service->id,
        'type' => 'end_of_billing',
        'reason' => 'changed my mind later',
        'processed_at' => now()->subMonths(2),
    ]);

    $open = CancellationRequest::create([
        'service_id' => $service->id,
        'type' => 'immediate',
        'reason' => 'leaving for good',
    ]);

    $stamped = $old->processed_at;

    $this->artisan('pnlcs:process-cancellations')->assertSuccessful();

    expect($open->fresh()->processed_at)->not->toBeNull();
    expect($old->fresh()->processed_at->toDateTimeString())->toBe($stamped->toDateTimeString());
});

test('a service with one request behaves as it always did', function () {
    Mail::fake();
    $service = leavingService();
    $request = CancellationRequest::create(['service_id' => $service->id, 'type' => 'immediate', 'reason' => 'done']);

    $this->artisan('pnlcs:process-cancellations')->assertSuccessful();

    expect(strtolower($service->fresh()->status))->toBe('cancelled');
    expect($request->fresh()->processed_at)->not->toBeNull();
});
