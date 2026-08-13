<?php

use App\Models\Client;
use App\Models\Product;
use App\Models\Server;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Modules\Servers\Panelica\PanelicaModule;

/*
 * Databases tab: create/delete databases + manage users, all fenced to the
 * account. The account owns domain 'dom-own'; 'dom-foreign' belongs to someone
 * else and must never be honoured. Database user 'u-own' is the account's;
 * 'u-foreign' is not.
 */

function dbServer(): Server
{
    return Server::create([
        'name' => 'Panel', 'hostname' => 'panel.test', 'ip_address' => '10.0.0.6',
        'type' => 'panelica', 'username' => 'u', 'password' => 'pk', 'access_hash' => 'sk',
        'port' => 8443, 'active' => true,
    ]);
}

function dbService(Server $server): array
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

function dbIntruder(): User
{
    $u = User::factory()->create();
    $u->clients()->attach(Client::factory()->create()->id);

    return $u;
}

/** One owned domain with one database ('site_db') + its primary user + one extra. */
function fakeDbApi(): void
{
    Http::fake(function ($request) {
        $url = $request->url();
        $m = $request->method();
        if (str_contains($url, '/accounts/') && str_contains($url, '/domains')) {
            return Http::response(['data' => [['id' => 'dom-own', 'domain_name' => 'example.com']]], 200);
        }
        if (str_contains($url, '/v1/databases?') && $m === 'GET') {
            return Http::response(['data' => [
                ['id' => 'u-own', 'username' => 'site_db', 'role' => 'dbOwner', 'is_primary' => true, 'database_name' => 'site_db'],
                ['id' => 'u-extra', 'username' => 'reader', 'role' => 'read', 'is_primary' => false, 'database_name' => 'site_db'],
            ]], 200);
        }
        if (str_contains($url, '/databases') && $m === 'POST') {
            return Http::response(['data' => ['id' => 'x']], 201);
        }
        if (str_contains($url, '/databases') && $m === 'DELETE') {
            return Http::response(['status' => 'success'], 200);
        }
        if (str_contains($url, '/v1/mysql-users/') && $m === 'POST') {
            return Http::response(['status' => 'success'], 200);
        }
        if (str_contains($url, '/v1/mysql-users/') && $m === 'DELETE') {
            return Http::response(['status' => 'success'], 200);
        }

        return Http::response(['data' => []], 200);
    });
}

it('offers the databases feature for a provisioned service', function () {
    [$u, $s] = dbService(dbServer());
    expect((new PanelicaModule)->hostingFeatures($s))->toContain('databases');
});

it('lists databases grouped by the account\'s own domains', function () {
    fakeDbApi();
    [$u, $s] = dbService(dbServer());

    $groups = (new PanelicaModule)->listDatabases($s);

    expect($groups)->toHaveCount(1)
        ->and($groups[0]['domain'])->toBe('example.com')
        ->and($groups[0]['users'])->toHaveCount(2);
    Http::assertSent(fn ($r) => str_contains($r->url(), '/v1/databases?') && str_contains($r->url(), 'domain_id=dom-own'));
});

it('refuses to create a database on a domain the account does not own', function () {
    fakeDbApi();
    [$u, $s] = dbService(dbServer());

    $r = (new PanelicaModule)->createDatabase($s, 'dom-foreign', 'shop', 'shopu', 'password123');

    expect($r['success'])->toBeFalse();
    Http::assertNotSent(fn ($rq) => $rq->method() === 'POST' && str_contains($rq->url(), '/databases'));
});

it('refuses invalid database identifiers before any request', function () {
    fakeDbApi();
    [$u, $s] = dbService(dbServer());

    expect((new PanelicaModule)->createDatabase($s, 'dom-own', 'bad-name!', 'u', 'password123')['success'])->toBeFalse();
    Http::assertNotSent(fn ($rq) => $rq->method() === 'POST' && str_contains($rq->url(), '/domains/dom-own/databases'));
});

it('creates a database on an owned domain', function () {
    fakeDbApi();
    [$u, $s] = dbService(dbServer());

    $r = (new PanelicaModule)->createDatabase($s, 'dom-own', 'shop', 'shopu', 'password123');

    expect($r['success'])->toBeTrue();
    Http::assertSent(fn ($rq) => $rq->method() === 'POST' && str_contains($rq->url(), '/v1/domains/dom-own/databases'));
});

it('reports friendly messages for missing-endpoint and quota', function () {
    Http::fake(function ($request) {
        if (str_contains($request->url(), '/accounts/') && str_contains($request->url(), '/domains')) {
            return Http::response(['data' => [['id' => 'dom-own', 'domain_name' => 'example.com']]], 200);
        }

        return Http::response(['error' => 'no'], 404);
    });
    [$u, $s] = dbService(dbServer());
    $r = (new PanelicaModule)->createDatabase($s, 'dom-own', 'shop', 'shopu', 'password123');
    expect($r['success'])->toBeFalse()->and(strtolower($r['message']))->toContain('not available');
});

it('fences database deletion to a database that appears under the account', function () {
    fakeDbApi();
    [$u, $s] = dbService(dbServer());

    $ok = (new PanelicaModule)->deleteDatabase($s, 'dom-own', 'site_db');
    $bad = (new PanelicaModule)->deleteDatabase($s, 'dom-own', 'someone_elses_db');

    expect($ok['success'])->toBeTrue()->and($bad['success'])->toBeFalse();
});

it('fences user deletion and password change to the account\'s own users', function () {
    fakeDbApi();
    [$u, $s] = dbService(dbServer());
    $mod = new PanelicaModule;

    expect($mod->deleteDatabaseUser($s, 'u-extra')['success'])->toBeTrue()
        ->and($mod->deleteDatabaseUser($s, 'u-foreign')['success'])->toBeFalse()
        ->and($mod->changeDatabaseUserPassword($s, 'u-foreign', 'password123')['success'])->toBeFalse();
});

it('defaults an unknown user role to readWrite', function () {
    fakeDbApi();
    [$u, $s] = dbService(dbServer());

    (new PanelicaModule)->createDatabaseUser($s, 'dom-own', 'someone', 'password123', 'superadmin');

    Http::assertSent(function ($r) {
        if ($r->method() !== 'POST' || ! str_contains($r->url(), '/v1/databases')) {
            return false;
        }
        return (json_decode($r->body(), true)['role'] ?? null) === 'readWrite';
    });
});

// ----- Controller gates -----

it('shows the databases tab to the owner', function () {
    fakeDbApi();
    [$u, $s] = dbService(dbServer());

    $this->actingAs($u)->get(route('client.services.databases', $s))->assertOk()->assertSee('site_db');
});

it('forbids the databases tab and every mutation for another client', function () {
    fakeDbApi();
    [$owner, $s] = dbService(dbServer());
    $intruder = dbIntruder();

    $this->actingAs($intruder)->get(route('client.services.databases', $s))->assertForbidden();

    foreach ([
        ['databases.store', ['domain_id' => 'dom-own', 'database_name' => 'x', 'database_user' => 'y', 'password' => 'password123']],
        ['databases.destroy', ['domain_id' => 'dom-own', 'database_name' => 'site_db']],
        ['databases.users.store', ['domain_id' => 'dom-own', 'username' => 'z', 'password' => 'password123', 'role' => 'read']],
        ['databases.users.destroy', ['user_id' => 'u-extra']],
        ['databases.users.password', ['user_id' => 'u-extra', 'password' => 'password123']],
    ] as [$route, $data]) {
        $this->actingAs($intruder)->post(route('client.services.'.$route, $s), $data)->assertForbidden();
    }
});

it('validates the create-database and role inputs', function () {
    fakeDbApi();
    [$u, $s] = dbService(dbServer());

    $this->actingAs($u)->from(route('client.services.databases', $s))
        ->post(route('client.services.databases.store', $s), ['domain_id' => 'dom-own', 'database_name' => 'bad name', 'database_user' => 'u', 'password' => 'password123'])
        ->assertSessionHasErrors('database_name');

    $this->actingAs($u)->from(route('client.services.databases', $s))
        ->post(route('client.services.databases.users.store', $s), ['domain_id' => 'dom-own', 'username' => 'u', 'password' => 'password123', 'role' => 'root'])
        ->assertSessionHasErrors('role');
});
