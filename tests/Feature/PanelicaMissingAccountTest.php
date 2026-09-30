<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ModuleQueue;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\Service;
use App\Services\ProvisioningService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/**
 * A Panelica account that is no longer on the panel.
 *
 * Panelica answers a suspend, unsuspend or terminate for a missing account
 * with 400 and "user not found". PNLCS did not count that as final, so the
 * queue reopened the work every morning, tried five times and raised the same
 * "will NOT be retried" alert, day after day. The panel gives the same answer
 * when its own lookup failed for another reason, so the module now asks for
 * the account itself: only a 404 there makes the refusal final.
 */
function panelicaServiceFor(string $userId): Service
{
    $client = Client::factory()->create();

    $server = Server::factory()->create([
        'type' => 'panelica', 'hostname' => 'panel.missing.test',
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
        'status' => 'active',
        'domain' => 'missing-account.test',
        'next_due_date' => now()->subDays(20),
        'override_auto_suspend_date' => null,
        'module_data' => ['panelica_user_id' => $userId],
    ]);

    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'status' => 'overdue',
        'due_date' => now()->subDays(10),
        'total' => 25,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'client_id' => $client->id,
        'type' => 'Hosting',
        'rel_id' => $service->id,
        'description' => 'Hosting',
        'amount' => 25,
        'taxed' => false,
    ]);

    return $service;
}

/** The panel refuses the action with "user not found"; the account lookup answers $lookupStatus. */
function panelSaysUserNotFound(string $userId, int $lookupStatus): void
{
    Http::fake(function (Request $request) use ($userId, $lookupStatus) {
        if ($request->method() === 'GET' && str_ends_with($request->url(), "/v1/accounts/{$userId}")) {
            return Http::response($lookupStatus === 404
                ? ['status' => 'error', 'error' => 'Account not found']
                : ['status' => 'success', 'data' => ['id' => $userId]], $lookupStatus);
        }

        return Http::response(['status' => 'error', 'error' => 'Suspend Failed', 'details' => 'user not found'], 400);
    });
}

beforeEach(function () {
    Mail::fake();
});

it('stops retrying a suspend once the panel confirms the account is gone', function () {
    $service = panelicaServiceFor('gone-1');
    panelSaysUserNotFound('gone-1', 404);

    $this->artisan('pnlcs:auto-suspend')->assertSuccessful();

    $entry = ModuleQueue::where('service_id', $service->id)->where('action', 'suspend')->firstOrFail();
    expect($entry->status)->toBe('failed')
        ->and($entry->next_attempt_at)->toBeNull()
        ->and($entry->last_error)->toContain('account does not exist')
        ->and(ProvisioningService::willNeverSucceed($entry->last_error))->toBeTrue();

    // The next night asks the queue first and leaves the panel alone.
    $calls = count(Http::recorded());
    $this->artisan('pnlcs:auto-suspend')->assertSuccessful();
    expect(count(Http::recorded()))->toBe($calls);
});

it('keeps retrying when the account is still there', function () {
    $service = panelicaServiceFor('present-1');
    panelSaysUserNotFound('present-1', 200);

    $this->artisan('pnlcs:auto-suspend')->assertSuccessful();

    $entry = ModuleQueue::where('service_id', $service->id)->where('action', 'suspend')->firstOrFail();
    expect($entry->status)->toBe('pending')
        ->and($entry->last_error)->toContain('user not found')
        ->and(ProvisioningService::willNeverSucceed($entry->last_error))->toBeFalse();
});

it('keeps retrying when the account lookup itself fails', function () {
    $service = panelicaServiceFor('unknown-1');
    panelSaysUserNotFound('unknown-1', 500);

    $this->artisan('pnlcs:auto-suspend')->assertSuccessful();

    expect(ModuleQueue::where('service_id', $service->id)->where('action', 'suspend')->value('status'))->toBe('pending');
});

it('ends the daily loop of an entry recorded before this fix', function () {
    // What the billing installation held: failed after five tries, with the
    // panel's raw answer, which the queue did not recognise as final.
    $service = panelicaServiceFor('gone-2');
    $entry = ModuleQueue::create([
        'service_id' => $service->id, 'action' => 'suspend', 'status' => 'failed',
        'attempts' => 5, 'max_attempts' => 5, 'next_attempt_at' => now()->subHour(),
        'last_error' => 'Suspend failed: {"details":"user not found","error":"Suspend Failed","status":"error"}',
    ]);
    panelSaysUserNotFound('gone-2', 404);

    $this->artisan('pnlcs:auto-suspend')->assertSuccessful();

    $entry->refresh();
    expect($entry->status)->toBe('failed')
        ->and($entry->attempts)->toBe(5)
        ->and($entry->last_error)->toContain('account does not exist');

    // Nothing is left for the queue to pick up.
    expect(ModuleQueue::due()->where('service_id', $service->id)->exists())->toBeFalse();
});

it('reports a terminate for a missing account as final too', function () {
    $service = panelicaServiceFor('gone-3');
    panelSaysUserNotFound('gone-3', 404);

    $result = app(ProvisioningService::class)->terminateAccount($service);

    expect($result['success'])->toBeFalse()
        ->and(ProvisioningService::willNeverSucceed($result['message']))->toBeTrue()
        ->and(ModuleQueue::where('service_id', $service->id)->where('action', 'terminate')->value('status'))->toBe('failed');
});

it('clears what the queue gave up on once the action goes through', function () {
    $service = panelicaServiceFor('back-1');
    ModuleQueue::create([
        'service_id' => $service->id, 'action' => 'suspend', 'status' => 'failed',
        'attempts' => 5, 'max_attempts' => 5,
        'last_error' => 'Suspend failed: the account does not exist on the Panelica server (user back-1).',
    ]);
    $provisioning = app(ProvisioningService::class);
    expect($provisioning->hasGivenUp($service, 'suspend'))->toBeTrue();

    // The operator put the account back; suspending by hand now works.
    Http::fake(['*' => Http::response(['status' => 'success'], 200)]);
    $result = $provisioning->suspendAccount($service, 'Overdue');

    expect($result['success'])->toBeTrue()
        ->and($provisioning->hasGivenUp($service->fresh(), 'suspend'))->toBeFalse()
        ->and(ModuleQueue::where('service_id', $service->id)->where('action', 'suspend')->value('status'))->toBe('completed');
});
