<?php

use App\Models\Client;
use App\Models\Product;
use App\Models\Server;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Modules\Servers\Panelica\PanelicaModule;

/*
 * Phase 3 of the Panelica client hosting area: the file manager.
 *
 * The security model has two fences. The panel fences every path to the
 * account's home directory server-side (a crafted escape is refused there);
 * these tests pin the PNLCS half: user_id is ALWAYS the service's own account,
 * never anything the customer can influence, and names are validated before a
 * request is sent. The controller's client-ownership gate is pinned too.
 */

function filesServer(): Server
{
    return Server::create([
        'name' => 'Panel', 'hostname' => 'panel.test', 'ip_address' => '10.0.0.5',
        'type' => 'panelica', 'username' => 'u', 'password' => 'pk', 'access_hash' => 'sk',
        'port' => 8443, 'active' => true,
    ]);
}

function filesService(Server $server, string $accountId = 'acct-1', string $username = 'sec123'): array
{
    $client = Client::factory()->create();
    $user = User::factory()->create();
    $user->clients()->attach($client->id);
    $product = Product::factory()->create(['server_type' => 'panelica']);
    $service = Service::factory()->create([
        'client_id' => $client->id, 'product_id' => $product->id, 'server_id' => $server->id,
        'status' => 'active', 'username' => $username, 'module_data' => ['panelica_user_id' => $accountId],
    ]);

    return [$user, $service, $client];
}

/** Fake the file endpoints; the directory holds one folder and one file. */
function fakeFilesApi(): void
{
    Http::fake(function ($request) {
        $url = $request->url();
        $method = $request->method();

        if (str_contains($url, '/v1/files/content') && $method === 'GET') {
            return Http::response(['data' => ['content' => "line one\nline two"]], 200);
        }
        if (str_contains($url, '/v1/files/download') && $method === 'GET') {
            return Http::response('binary-bytes', 200);
        }
        if (str_contains($url, '/v1/files/content') && $method === 'PUT') {
            return Http::response(['status' => 'success'], 200);
        }
        if (str_contains($url, '/v1/files/rename') && $method === 'PATCH') {
            return Http::response(['status' => 'success'], 200);
        }
        if (str_contains($url, '/v1/files') && $method === 'GET') {
            return Http::response(['data' => [
                'path' => '/home/sec123',
                'files' => [
                    ['name' => 'public_html', 'type' => 'folder', 'path' => '/home/sec123/public_html', 'size' => 4096, 'permissions_octal' => '755'],
                    ['name' => 'index.html', 'type' => 'file', 'path' => '/home/sec123/index.html', 'size' => 12, 'size_formatted' => '12 B', 'extension' => 'html', 'permissions_octal' => '644'],
                ],
            ]], 200);
        }
        if (str_contains($url, '/v1/files') && $method === 'POST') {
            return Http::response(['status' => 'success'], 201);
        }
        if (str_contains($url, '/v1/files') && $method === 'DELETE') {
            return Http::response(['status' => 'success'], 200);
        }

        return Http::response(['data' => []], 200);
    });
}

it('offers the files feature for a provisioned service', function () {
    [$user, $service] = filesService(filesServer());
    expect((new PanelicaModule)->hostingFeatures($service))->toContain('files');
});

it('lists a directory scoped to the service\'s own account', function () {
    fakeFilesApi();
    [$user, $service] = filesService(filesServer());

    $listing = (new PanelicaModule)->listFiles($service);

    expect($listing['ok'])->toBeTrue()
        ->and($listing['home'])->toBe('/home/sec123')
        ->and($listing['entries'])->toHaveCount(2);

    // The account id is injected server-side, always - never from user input.
    Http::assertSent(fn ($r) => $r->method() === 'GET'
        && str_contains($r->url(), '/v1/files?')
        && str_contains($r->url(), 'user_id=acct-1'));
});

it('always sends the service\'s account id even when a path is supplied', function () {
    fakeFilesApi();
    [$user, $service] = filesService(filesServer());

    (new PanelicaModule)->listFiles($service, '/home/sec123/public_html');

    Http::assertSent(fn ($r) => str_contains($r->url(), 'user_id=acct-1')
        && str_contains($r->url(), 'public_html'));
});

it('refuses a folder name containing a slash without calling the panel', function () {
    fakeFilesApi();
    [$user, $service] = filesService(filesServer());

    $result = (new PanelicaModule)->createEntry($service, '/home/sec123', '../evil', 'folder');

    expect($result['success'])->toBeFalse();
    Http::assertNotSent(fn ($r) => $r->method() === 'POST');
});

it('refuses a rename to a traversing name without calling the panel', function () {
    fakeFilesApi();
    [$user, $service] = filesService(filesServer());

    $result = (new PanelicaModule)->renameEntry($service, '/home/sec123/index.html', '../x');

    expect($result['success'])->toBeFalse();
    Http::assertNotSent(fn ($r) => $r->method() === 'PATCH');
});

it('reads a text file for the editor', function () {
    fakeFilesApi();
    [$user, $service] = filesService(filesServer());

    $result = (new PanelicaModule)->readFile($service, '/home/sec123/index.html');

    expect($result['success'])->toBeTrue()
        ->and($result['data']['content'])->toContain('line one');
});

it('deletes paths through the DELETE body channel', function () {
    fakeFilesApi();
    [$user, $service] = filesService(filesServer());

    $result = (new PanelicaModule)->deleteEntries($service, ['/home/sec123/index.html']);

    expect($result['success'])->toBeTrue();
    Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_contains($r->url(), '/v1/files'));
});

// ----- Controller ownership gate -----

it('shows the file manager to the service owner', function () {
    fakeFilesApi();
    [$user, $service] = filesService(filesServer());

    $this->actingAs($user)
        ->get(route('client.services.files', $service))
        ->assertOk()
        ->assertSee('index.html')
        ->assertSee('public_html');
});

it('forbids the file manager for another client\'s service', function () {
    fakeFilesApi();
    [$owner, $service] = filesService(filesServer());
    $intruder = User::factory()->create();
    $intruder->clients()->attach(Client::factory()->create()->id);

    $this->actingAs($intruder)
        ->get(route('client.services.files', $service))
        ->assertForbidden();
});

it('forbids file mutations on another client\'s service', function () {
    fakeFilesApi();
    [$owner, $service] = filesService(filesServer());
    $intruder = User::factory()->create();
    $intruder->clients()->attach(Client::factory()->create()->id);

    $this->actingAs($intruder)
        ->post(route('client.services.files.delete', $service), ['paths' => ['/home/sec123/index.html']])
        ->assertForbidden();

    $this->actingAs($intruder)
        ->get(route('client.services.files.download', ['service' => $service, 'path' => '/etc/passwd']))
        ->assertForbidden();

    Http::assertNotSent(fn ($r) => $r->method() === 'DELETE');
});
