<?php

use App\Models\Client;
use App\Models\ModuleQueue;
use App\Models\Service;
use App\Services\ProvisioningService;

/**
 * The pruning job throwing away the panel's memory of what it gave up on.
 *
 * When a module action can never succeed - the account is not on the server,
 * the service carries no username - the queue marks the entry failed and the
 * operator is told it "will NOT be retried". Three jobs keep that promise by
 * asking hasGivenUp(), which reads exactly those failed rows: auto-suspend,
 * unsuspend-on-payment and the cancellation run.
 *
 * The pruning job deleted module_queue rows older than thirty days whatever
 * their status, failed ones included. A month after that decision the record of
 * it was gone: the crons started calling the module again for an account that
 * does not exist, queued the work afresh, retried it through the whole backoff,
 * and raised the same permanent-failure alert - the one that says it will not
 * be retried - all over again, every thirty days for as long as the service
 * exists.
 */
function queuedEntry(string $status, string $error, int $daysOld): ModuleQueue
{
    $service = Service::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'server_id' => null,
        'status' => 'active',
        'domain' => 'pruned-example.com',
    ]);

    $entry = ModuleQueue::create([
        'service_id' => $service->id,
        'action' => 'suspend',
        'status' => $status,
        'attempts' => 5,
        'max_attempts' => 5,
        'last_error' => $error,
    ]);

    // created_at is what the pruning job measures age on.
    $entry->forceFill(['created_at' => now()->subDays($daysOld)])->save();

    return $entry;
}

it('keeps the record of an action that can never succeed', function () {
    $entry = queuedEntry('failed', 'Suspend failed: no account on this server', 60);

    $this->artisan('pnlcs:prune-logs')->assertSuccessful();

    expect(ModuleQueue::find($entry->id))->not->toBeNull();

    // And the promise made to the operator still holds.
    expect(app(ProvisioningService::class)->hasGivenUp($entry->service, 'suspend'))->toBeTrue();
});

it('still clears out work that finished', function () {
    $done = queuedEntry('completed', '', 60);
    $cancelled = queuedEntry('cancelled', 'Service no longer exists', 60);

    $this->artisan('pnlcs:prune-logs')->assertSuccessful();

    expect(ModuleQueue::find($done->id))->toBeNull()
        ->and(ModuleQueue::find($cancelled->id))->toBeNull();
});

it('leaves work still waiting to run alone', function () {
    $pending = queuedEntry('pending', '', 60);

    $this->artisan('pnlcs:prune-logs')->assertSuccessful();

    expect(ModuleQueue::find($pending->id))->not->toBeNull();
});

it('says what it deleted', function () {
    queuedEntry('completed', '', 60);

    $this->artisan('pnlcs:prune-logs')
        ->expectsOutputToContain('module_queue')
        ->assertSuccessful();
});
