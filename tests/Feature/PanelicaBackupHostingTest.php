<?php

use App\Models\Client;
use App\Models\Product;
use App\Models\Server;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Modules\Servers\Panelica\PanelicaModule;

/*
 * Backups: the panel's list endpoint answers with everything the API key may
 * see and the PNLCS key is ROOT-scoped, so the fence is what keeps one customer
 * out of another's restore points. Creation always names the account's own
 * domains explicitly — otherwise the panel would archive the whole box.
 * Restore is intentionally absent from billing.
 */

function bkServer(): Server
{
    return Server::create([
        'name' => 'Panel', 'hostname' => 'panel.test', 'ip_address' => '10.0.0.6',
        'type' => 'panelica', 'username' => 'u', 'password' => 'pk', 'access_hash' => 'sk',
        'port' => 8443, 'active' => true,
    ]);
}

function bkService(Server $server): array
{
    $client = Client::factory()->create();
    $user = User::factory()->create();
    $user->clients()->attach($client->id);
    $product = Product::factory()->create(['server_type' => 'panelica']);
    $service = Service::factory()->create([
        'client_id' => $client->id, 'product_id' => $product->id, 'server_id' => $server->id,
        'status' => 'active', 'module_data' => ['panelica_user_id' => 'acct-1'],
    ]);

    return [$user, $service];
}

function fakeBackupApi(bool $enabled, array $backups = []): void
{
    Http::fake(function ($request) use ($enabled, $backups) {
        $url = $request->url();
        $m = $request->method();
        if (str_contains($url, '/accounts/') && str_contains($url, '/domains')) {
            return Http::response(['data' => [['id' => 'dom-own', 'domain_name' => 'example.com']]], 200);
        }
        if (preg_match('#/v1/accounts/[^/?]+$#', parse_url($url, PHP_URL_PATH) ?? '') && $m === 'GET') {
            return Http::response(['data' => ['id' => 'acct-1', 'plan_id' => 'plan-1']], 200);
        }
        if (str_contains($url, '/v1/plans') && $m === 'GET') {
            return Http::response(['data' => [['id' => 'plan-1', 'backup_enabled' => $enabled]]], 200);
        }
        if (str_contains($url, '/v1/backups') && $m === 'GET') {
            return Http::response(['data' => $backups], 200);
        }
        if (str_contains($url, '/v1/backups') && $m === 'POST') {
            return Http::response(['status' => 'success'], 201);
        }
        if (str_contains($url, '/v1/backups/') && $m === 'DELETE') {
            return Http::response(['status' => 'success'], 200);
        }

        return Http::response(['data' => []], 200);
    });
}

$MINE = ['backup_id' => 'b1', 'filename' => 'mine.tar.gz', 'backup_name' => 'nightly', 'size_mb' => 2048.0, 'domain_names' => ['example.com'], 'created_at' => '2026-08-14T02:00:00Z', 'status' => 'completed', 'backup_type' => 'full', 'encrypted' => true];
$THEIRS = ['backup_id' => 'b2', 'filename' => 'theirs.tar.gz', 'size_mb' => 10.0, 'domain_names' => ['someone-else.com'], 'created_at' => '2026-08-14T03:00:00Z', 'status' => 'completed'];
$MIXED = ['backup_id' => 'b3', 'filename' => 'mixed.tar.gz', 'size_mb' => 10.0, 'domain_names' => ['example.com', 'someone-else.com'], 'created_at' => '2026-08-14T04:00:00Z', 'status' => 'completed'];

it('offers the backups feature', function () {
    [$u, $s] = bkService(bkServer());
    expect((new PanelicaModule)->hostingFeatures($s))->toContain('backups');
});

it('shows only backups made of the account\'s own domains', function () use ($MINE, $THEIRS, $MIXED) {
    fakeBackupApi(true, [$MINE, $THEIRS, $MIXED]);
    [$u, $s] = bkService(bkServer());
    $list = (new PanelicaModule)->backups($s);

    // A ROOT-scoped key sees every archive on the box; only ours may surface,
    // and an archive that also covers someone else's domain is not ours.
    expect($list)->toHaveCount(1)
        ->and($list[0]['filename'])->toBe('mine.tar.gz')
        ->and($list[0]['name'])->toBe('nightly')
        ->and($list[0]['encrypted'])->toBeTrue();
});

it('reports the plan policy when backups are included', function () use ($MINE) {
    fakeBackupApi(true, [$MINE]);
    [$u, $s] = bkService(bkServer());
    expect((new PanelicaModule)->backupPolicy($s))->toMatchArray(['enabled' => true, 'count' => 1, 'can_create' => true]);
});

it('reports the plan policy when backups are excluded', function () use ($MINE) {
    fakeBackupApi(false, [$MINE]);
    [$u, $s] = bkService(bkServer());
    expect((new PanelicaModule)->backupPolicy($s))->toMatchArray(['enabled' => false, 'can_create' => false]);
});

it('refuses to create when the plan excludes backups — no request sent', function () {
    fakeBackupApi(false, []);
    [$u, $s] = bkService(bkServer());
    expect((new PanelicaModule)->createBackup($s)['success'])->toBeFalse();
    Http::assertNotSent(fn ($rq) => $rq->method() === 'POST' && str_contains($rq->url(), '/v1/backups'));
});

it('always names the account\'s own domains when creating', function () {
    fakeBackupApi(true, []);
    [$u, $s] = bkService(bkServer());
    $mod = new PanelicaModule;

    // No domain given → every domain of THIS account, never a bare request that
    // would let the panel archive the whole box.
    expect($mod->createBackup($s, null, 'pre-update')['success'])->toBeTrue();
    Http::assertSent(fn ($rq) => $rq->method() === 'POST' && str_contains($rq->url(), '/v1/backups')
        && ($rq->data()['domain_ids'] ?? []) === ['dom-own']
        && ($rq->data()['backup_name'] ?? null) === 'pre-update');

    // A foreign domain is refused outright.
    expect($mod->createBackup($s, 'dom-foreign')['success'])->toBeFalse();
});

it('fences deletion to the account\'s own backups', function () use ($MINE, $THEIRS) {
    fakeBackupApi(true, [$MINE, $THEIRS]);
    [$u, $s] = bkService(bkServer());
    $mod = new PanelicaModule;

    expect($mod->deleteBackup($s, 'mine.tar.gz')['success'])->toBeTrue()
        ->and($mod->deleteBackup($s, 'theirs.tar.gz')['success'])->toBeFalse();
    Http::assertNotSent(fn ($rq) => $rq->method() === 'DELETE' && str_contains($rq->url(), 'theirs'));
});

it('shows the backups tab and forbids other clients', function () use ($MINE) {
    fakeBackupApi(true, [$MINE]);
    [$owner, $s] = bkService(bkServer());
    $this->actingAs($owner)->get(route('client.services.backups', $s))->assertOk()->assertSee('nightly');

    $intruder = User::factory()->create();
    $intruder->clients()->attach(Client::factory()->create()->id);
    $this->actingAs($intruder)->get(route('client.services.backups', $s))->assertForbidden();
    $this->actingAs($intruder)->post(route('client.services.backups.store', $s), [])->assertForbidden();
    $this->actingAs($intruder)->post(route('client.services.backups.destroy', $s), ['filename' => 'mine.tar.gz'])->assertForbidden();
});
