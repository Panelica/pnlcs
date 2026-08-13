<?php

use App\Models\Client;
use App\Models\Product;
use App\Models\Server;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Modules\Servers\Panelica\PanelicaModule;

/*
 * Comprehensive file-manager coverage: every module operation's payload and
 * fence, every controller action's ownership gate and validation, plus the edge
 * cases. The security invariant under test throughout: user_id on every request
 * is the SERVICE's own account, fixed server-side, never anything a customer can
 * supply; and names are validated before any request leaves PNLCS.
 */

function fmServer(): Server
{
    return Server::create([
        'name' => 'Panel', 'hostname' => 'panel.test', 'ip_address' => '10.0.0.8',
        'type' => 'panelica', 'username' => 'u', 'password' => 'pk', 'access_hash' => 'sk',
        'port' => 8443, 'active' => true,
    ]);
}

function fmService(Server $server, string $account = 'acct-9', string $username = 'sec777'): array
{
    $client = Client::factory()->create();
    $user = User::factory()->create();
    $user->clients()->attach($client->id);
    $product = Product::factory()->create(['server_type' => 'panelica']);
    $service = Service::factory()->create([
        'client_id' => $client->id, 'product_id' => $product->id, 'server_id' => $server->id,
        'status' => 'active', 'username' => $username, 'module_data' => ['panelica_user_id' => $account],
    ]);

    return [$user, $service, $client];
}

/** A second, unrelated client used to prove the ownership gate. */
function fmIntruder(): User
{
    $u = User::factory()->create();
    $u->clients()->attach(Client::factory()->create()->id);

    return $u;
}

function fmApiOk(): void
{
    Http::fake(function ($request) {
        $url = $request->url();
        $m = $request->method();
        if (str_contains($url, '/v1/files/content') && $m === 'GET') {
            return Http::response(['data' => ['content' => 'hello editor']], 200);
        }
        if (str_contains($url, '/v1/files/content') && $m === 'PUT') {
            return Http::response(['status' => 'success'], 200);
        }
        if (str_contains($url, '/v1/files/download')) {
            return Http::response('DOWNLOAD-BYTES', 200);
        }
        if (str_contains($url, '/v1/files/rename')) {
            return Http::response(['status' => 'success'], 200);
        }
        if (str_contains($url, '/v1/files/upload')) {
            return Http::response(['data' => ['success' => true]], 200);
        }
        if (str_contains($url, '/v1/files') && $m === 'GET') {
            return Http::response(['data' => ['path' => '/home/sec777', 'files' => [
                ['name' => 'app.php', 'type' => 'file', 'path' => '/home/sec777/app.php', 'size' => 20, 'extension' => 'php'],
            ]]], 200);
        }
        if (str_contains($url, '/v1/files') && $m === 'POST') {
            return Http::response(['status' => 'success'], 201);
        }
        if (str_contains($url, '/v1/files') && $m === 'DELETE') {
            return Http::response(['status' => 'success'], 200);
        }

        return Http::response(['data' => []], 200);
    });
}

/** Body of the first request matching $method+$needle, decoded. */
function fmSentBody(string $method, string $needle): array
{
    foreach (Http::recorded() as [$request]) {
        if ($request->method() === $method && str_contains($request->url(), $needle)) {
            return json_decode($request->body(), true) ?? [];
        }
    }

    return [];
}

// ---------------------------------------------------------------------------
// Module: create / rename / write payloads carry the account id, always
// ---------------------------------------------------------------------------

it('creates a folder with the account id fixed server-side', function () {
    fmApiOk();
    [$u, $s] = fmService(fmServer());

    $r = (new PanelicaModule)->createEntry($s, '/home/sec777', 'public_html', 'folder');

    expect($r['success'])->toBeTrue();
    $body = fmSentBody('POST', '/v1/files');
    expect($body['user_id'])->toBe('acct-9')
        ->and($body['type'])->toBe('folder')
        ->and($body['name'])->toBe('public_html');
});

it('creates a text file with content', function () {
    fmApiOk();
    [$u, $s] = fmService(fmServer());

    $r = (new PanelicaModule)->createEntry($s, '/home/sec777', 'notes.txt', 'file', 'body text');

    expect($r['success'])->toBeTrue();
    $body = fmSentBody('POST', '/v1/files');
    expect($body['type'])->toBe('file')->and($body['content'])->toBe('body text');
});

it('coerces an unknown entry type to file', function () {
    fmApiOk();
    [$u, $s] = fmService(fmServer());

    (new PanelicaModule)->createEntry($s, '/home/sec777', 'x', 'device');

    expect(fmSentBody('POST', '/v1/files')['type'])->toBe('file');
});

it('rejects every unsafe entry name before any request', function () {
    fmApiOk();
    [$u, $s] = fmService(fmServer());
    $mod = new PanelicaModule;

    foreach (['', '.', '..', 'a/b', "x\0y'"] as $bad) {
        expect($mod->createEntry($s, '/home/sec777', $bad, 'folder')['success'])->toBeFalse();
    }
    Http::assertNotSent(fn ($r) => $r->method() === 'POST' && str_contains($r->url(), '/v1/files'));
});

it('renames with the account id fixed and a valid new name', function () {
    fmApiOk();
    [$u, $s] = fmService(fmServer());

    $r = (new PanelicaModule)->renameEntry($s, '/home/sec777/app.php', 'index.php');

    expect($r['success'])->toBeTrue();
    $body = fmSentBody('PATCH', '/v1/files/rename');
    expect($body['user_id'])->toBe('acct-9')
        ->and($body['path'])->toBe('/home/sec777/app.php')
        ->and($body['new_name'])->toBe('index.php');
});

it('writes file content with the account id fixed', function () {
    fmApiOk();
    [$u, $s] = fmService(fmServer());

    $r = (new PanelicaModule)->writeFile($s, '/home/sec777/app.php', '<?php echo 1;');

    expect($r['success'])->toBeTrue();
    $body = fmSentBody('PUT', '/v1/files/content');
    expect($body['user_id'])->toBe('acct-9')->and($body['content'])->toBe('<?php echo 1;');
});

it('returns a download response on success and null on failure', function () {
    Http::fake([
        '*/v1/files/download*' => Http::sequence()
            ->push('BYTES', 200)
            ->push('nope', 500),
    ]);
    [$u, $s] = fmService(fmServer());
    $mod = new PanelicaModule;

    expect($mod->downloadFile($s, '/home/sec777/a')?->body())->toBe('BYTES')
        ->and($mod->downloadFile($s, '/home/sec777/b'))->toBeNull();
});

it('reports a folder that cannot be opened without throwing', function () {
    Http::fake(['*/v1/files*' => Http::response(['error' => 'denied'], 403)]);
    [$u, $s] = fmService(fmServer());

    $listing = (new PanelicaModule)->listFiles($s, '/home/sec777/secret');

    expect($listing['ok'])->toBeFalse()->and($listing['entries'])->toBe([]);
});

it('defaults to the account home when no path is given', function () {
    fmApiOk();
    [$u, $s] = fmService(fmServer());

    (new PanelicaModule)->listFiles($s);

    // No explicit path => the panel defaults to home; only user_id is sent.
    Http::assertSent(fn ($r) => str_contains($r->url(), 'user_id=acct-9') && ! str_contains($r->url(), 'path='));
});

// ---------------------------------------------------------------------------
// Controller: editor
// ---------------------------------------------------------------------------

it('loads a text file into the editor for the owner', function () {
    fmApiOk();
    [$u, $s] = fmService(fmServer());

    $this->actingAs($u)
        ->get(route('client.services.files.edit', ['service' => $s, 'path' => '/home/sec777/app.php']))
        ->assertOk()
        ->assertSee('hello editor');
});

it('redirects back to the folder when a file cannot be read', function () {
    Http::fake(['*/v1/files/content*' => Http::response(['error' => 'no'], 404)]);
    [$u, $s] = fmService(fmServer());

    $this->actingAs($u)
        ->get(route('client.services.files.edit', ['service' => $s, 'path' => '/home/sec777/missing.php']))
        ->assertRedirect();
});

it('forbids opening the editor on another client\'s service', function () {
    fmApiOk();
    [$owner, $s] = fmService(fmServer());

    $this->actingAs(fmIntruder())
        ->get(route('client.services.files.edit', ['service' => $s, 'path' => '/home/sec777/app.php']))
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// Controller: save / create / rename / delete / download / upload gates
// ---------------------------------------------------------------------------

it('saves an edited file for the owner and returns to the folder', function () {
    fmApiOk();
    [$u, $s] = fmService(fmServer());

    $this->actingAs($u)
        ->post(route('client.services.files.save', $s), ['path' => '/home/sec777/app.php', 'content' => 'x'])
        ->assertRedirect();
});

it('requires file or folder as the create type', function () {
    fmApiOk();
    [$u, $s] = fmService(fmServer());

    $this->actingAs($u)
        ->from(route('client.services.files', $s))
        ->post(route('client.services.files.create', $s), ['path' => '/home/sec777', 'name' => 'x', 'type' => 'device'])
        ->assertSessionHasErrors('type');
});

it('requires a path to save', function () {
    fmApiOk();
    [$u, $s] = fmService(fmServer());

    $this->actingAs($u)
        ->from(route('client.services.files', $s))
        ->post(route('client.services.files.save', $s), ['content' => 'x'])
        ->assertSessionHasErrors('path');
});

it('requires a file to upload', function () {
    fmApiOk();
    [$u, $s] = fmService(fmServer());

    $this->actingAs($u)
        ->from(route('client.services.files', $s))
        ->post(route('client.services.files.upload', $s), ['path' => '/home/sec777'])
        ->assertSessionHasErrors('file');
});

it('streams a download to the owner with an attachment header', function () {
    fmApiOk();
    [$u, $s] = fmService(fmServer());

    $res = $this->actingAs($u)
        ->get(route('client.services.files.download', ['service' => $s, 'path' => '/home/sec777/app.php']));

    $res->assertOk();
    expect($res->headers->get('content-disposition'))->toContain('app.php');
});

it('forbids every file mutation on another client\'s service', function () {
    fmApiOk();
    [$owner, $s] = fmService(fmServer());
    $intruder = fmIntruder();

    foreach ([
        ['files.save', ['path' => '/home/sec777/a', 'content' => 'x']],
        ['files.create', ['path' => '/home/sec777', 'name' => 'x', 'type' => 'folder']],
        ['files.rename', ['path' => '/home/sec777/a', 'new_name' => 'b']],
        ['files.delete', ['paths' => ['/home/sec777/a']]],
        ['files.upload', ['path' => '/home/sec777']],
    ] as [$route, $data]) {
        $this->actingAs($intruder)
            ->post(route('client.services.'.$route, $s), $data)
            ->assertForbidden();
    }
});
