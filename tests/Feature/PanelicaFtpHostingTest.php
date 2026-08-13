<?php

use App\Models\Client;
use App\Models\Product;
use App\Models\Server;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Modules\Servers\Panelica\PanelicaModule;

/*
 * FTP accounts: per-account (user_id), the customer sees only their own, changes
 * passwords, deletes, and may create ONLY when the plan allows it (ftp_access_
 * enabled) and is under max_ftp_accounts. The panel enforces this server-side;
 * these tests pin the PNLCS fence + policy gate + ownership + controller gates.
 */

function ftpServer(): Server
{
    return Server::create([
        'name' => 'Panel', 'hostname' => 'panel.test', 'ip_address' => '10.0.0.4',
        'type' => 'panelica', 'username' => 'u', 'password' => 'pk', 'access_hash' => 'sk',
        'port' => 8443, 'active' => true,
    ]);
}

function ftpService_(Server $server): array
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

/** $plan = ['ftp_access_enabled'=>bool,'max_ftp_accounts'=>int]; $accounts = existing FTP rows. */
function fakeFtpApi(array $plan, array $accounts = []): void
{
    Http::fake(function ($request) use ($plan, $accounts) {
        $url = $request->url();
        $m = $request->method();
        if (str_contains($url, '/v1/accounts/') && str_contains($url, '/domains')) {
            return Http::response(['data' => [['id' => 'dom-1', 'domain_name' => 'example.com']]], 200);
        }
        if (preg_match('#/v1/accounts/[^/?]+$#', parse_url($url, PHP_URL_PATH) ?? '') && $m === 'GET') {
            return Http::response(['data' => ['id' => 'acct-1', 'plan_id' => 'plan-1']], 200);
        }
        if (str_contains($url, '/v1/plans') && $m === 'GET') {
            return Http::response(['data' => [array_merge(['id' => 'plan-1'], $plan)]], 200);
        }
        if (str_contains($url, '/v1/ftp-accounts') && $m === 'GET') {
            return Http::response(['data' => $accounts], 200);
        }
        if (str_contains($url, '/v1/ftp-accounts') && $m === 'POST') {
            return Http::response(['data' => ['id' => 'new']], 201);
        }
        if (str_contains($url, '/change-password') && $m === 'POST') {
            return Http::response(['status' => 'success'], 200);
        }
        if (str_contains($url, '/v1/ftp-accounts/') && $m === 'DELETE') {
            return Http::response(['status' => 'success'], 200);
        }

        return Http::response(['data' => []], 200);
    });
}

$OWN = ['id' => 'f-own', 'ftp_username' => 'siteftp', 'user_id' => 'acct-1', 'home_directory' => '/home/u', 'quota_mb' => 0, 'status' => 'active'];
$FOREIGN = ['id' => 'f-foreign', 'ftp_username' => 'other', 'user_id' => 'acct-99', 'home_directory' => '/home/x'];

it('offers the ftp feature for a provisioned service', function () {
    [$u, $s] = ftpService_(ftpServer());
    expect((new PanelicaModule)->hostingFeatures($s))->toContain('ftp');
});

it('lists only the account\'s own ftp users', function () use ($OWN, $FOREIGN) {
    fakeFtpApi(['ftp_access_enabled' => true, 'max_ftp_accounts' => 5], [$OWN, $FOREIGN]);
    [$u, $s] = ftpService_(ftpServer());

    $list = (new PanelicaModule)->ftpAccounts($s);

    expect($list)->toHaveCount(1)->and($list[0]['username'])->toBe('siteftp');
});

it('reads the plan policy: enabled + under limit means can create', function () use ($OWN) {
    fakeFtpApi(['ftp_access_enabled' => true, 'max_ftp_accounts' => 2], [$OWN]);
    [$u, $s] = ftpService_(ftpServer());

    $p = (new PanelicaModule)->ftpPolicy($s);
    expect($p['enabled'])->toBeTrue()->and($p['max'])->toBe(2)->and($p['used'])->toBe(1)->and($p['can_create'])->toBeTrue();
});

it('refuses to create when the plan disables ftp — no request sent', function () {
    fakeFtpApi(['ftp_access_enabled' => false, 'max_ftp_accounts' => 5], []);
    [$u, $s] = ftpService_(ftpServer());

    $r = (new PanelicaModule)->createFtpAccount($s, 'ftpuser', 'password123');
    expect($r['success'])->toBeFalse();
    Http::assertNotSent(fn ($rq) => $rq->method() === 'POST' && str_contains($rq->url(), '/v1/ftp-accounts'));
});

it('refuses to create when the plan limit is reached — no request sent', function () use ($OWN) {
    fakeFtpApi(['ftp_access_enabled' => true, 'max_ftp_accounts' => 1], [$OWN]);
    [$u, $s] = ftpService_(ftpServer());

    $r = (new PanelicaModule)->createFtpAccount($s, 'ftpuser', 'password123');
    expect($r['success'])->toBeFalse();
    Http::assertNotSent(fn ($rq) => $rq->method() === 'POST' && str_contains($rq->url(), '/v1/ftp-accounts'));
});

it('creates when allowed and scopes the home to a chosen domain', function () {
    fakeFtpApi(['ftp_access_enabled' => true, 'max_ftp_accounts' => 5], []);
    [$u, $s] = ftpService_(ftpServer());

    $r = (new PanelicaModule)->createFtpAccount($s, 'ftpuser', 'password123', 'dom-1', 100);
    expect($r['success'])->toBeTrue();
    Http::assertSent(function ($rq) {
        if ($rq->method() !== 'POST' || ! str_contains($rq->url(), '/v1/ftp-accounts')) {
            return false;
        }
        $b = json_decode($rq->body(), true);
        return ($b['user_id'] ?? null) === 'acct-1' && ($b['home_directory'] ?? null) === '/example.com';
    });
});

it('fences delete and password change to the account\'s own ftp users', function () use ($OWN) {
    fakeFtpApi(['ftp_access_enabled' => true, 'max_ftp_accounts' => 5], [$OWN]);
    [$u, $s] = ftpService_(ftpServer());
    $mod = new PanelicaModule;

    expect($mod->deleteFtpAccount($s, 'f-own')['success'])->toBeTrue()
        ->and($mod->deleteFtpAccount($s, 'f-foreign')['success'])->toBeFalse()
        ->and($mod->changeFtpPassword($s, 'f-foreign', 'password123')['success'])->toBeFalse();
});

// ----- Controller -----

it('shows the ftp tab to the owner and hides create when the plan disables it', function () {
    fakeFtpApi(['ftp_access_enabled' => false, 'max_ftp_accounts' => 0], []);
    [$u, $s] = ftpService_(ftpServer());

    $this->actingAs($u)->get(route('client.services.ftp', $s))
        ->assertOk()
        ->assertSee('does not include FTP');
});

it('forbids the ftp tab and mutations for another client', function () {
    fakeFtpApi(['ftp_access_enabled' => true, 'max_ftp_accounts' => 5], []);
    [$owner, $s] = ftpService_(ftpServer());
    $intruder = User::factory()->create();
    $intruder->clients()->attach(Client::factory()->create()->id);

    $this->actingAs($intruder)->get(route('client.services.ftp', $s))->assertForbidden();
    foreach ([
        ['ftp.store', ['username' => 'x', 'password' => 'password123']],
        ['ftp.destroy', ['ftp_id' => 'f-own']],
        ['ftp.password', ['ftp_id' => 'f-own', 'password' => 'password123']],
    ] as [$route, $data]) {
        $this->actingAs($intruder)->post(route('client.services.'.$route, $s), $data)->assertForbidden();
    }
});
