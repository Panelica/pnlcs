<?php

use App\Models\Client;
use App\Models\Product;
use App\Models\Server;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Modules\Servers\Panelica\PanelicaModule;

/*
 * Per-domain user cron: each job runs AS the domain owner's unprivileged system
 * user (pn-cron-exec, isolated) and is scheduled by the panel. PNLCS fences to
 * the account's own domains and gates creation on the plan's cron_jobs_enabled +
 * max_cron_jobs. These tests pin the fence, the plan gate, ownership, and the
 * controller gates.
 */

function crServer(): Server
{
    return Server::create([
        'name' => 'Panel', 'hostname' => 'panel.test', 'ip_address' => '10.0.0.4',
        'type' => 'panelica', 'username' => 'u', 'password' => 'pk', 'access_hash' => 'sk',
        'port' => 8443, 'active' => true,
    ]);
}

function crService(Server $server): array
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

/** @param array $jobs existing cron jobs (each needs id + domain_id) */
function fakeCronApi(bool $enabled, int $max, array $jobs = []): void
{
    Http::fake(function ($request) use ($enabled, $max, $jobs) {
        $url = $request->url();
        $m = $request->method();
        if (str_contains($url, '/accounts/') && str_contains($url, '/domains')) {
            return Http::response(['data' => [['id' => 'dom-own', 'domain_name' => 'example.com']]], 200);
        }
        if (preg_match('#/v1/accounts/[^/?]+$#', parse_url($url, PHP_URL_PATH) ?? '') && $m === 'GET') {
            return Http::response(['data' => ['id' => 'acct-1', 'plan_id' => 'plan-1']], 200);
        }
        if (str_contains($url, '/v1/plans') && $m === 'GET') {
            return Http::response(['data' => [['id' => 'plan-1', 'cron_jobs_enabled' => $enabled, 'max_cron_jobs' => $max]]], 200);
        }
        if (str_contains($url, '/cron-jobs') && str_contains($url, '/toggle') && $m === 'POST') {
            return Http::response(['status' => 'success'], 200);
        }
        if (str_contains($url, '/cron-jobs') && str_contains($url, '/run') && $m === 'POST') {
            return Http::response(['status' => 'success', 'data' => ['output' => 'ran-ok']], 200);
        }
        if (preg_match('#/v1/cron-jobs/[^/?]+$#', parse_url($url, PHP_URL_PATH) ?? '') && $m === 'DELETE') {
            return Http::response(['status' => 'success'], 200);
        }
        if (str_contains($url, '/cron-jobs') && $m === 'GET') {
            return Http::response(['data' => $jobs], 200);
        }
        if (str_contains($url, '/cron-jobs') && $m === 'POST') {
            return Http::response(['status' => 'success', 'data' => ['id' => 'new']], 201);
        }

        return Http::response(['data' => []], 200);
    });
}

$JOB = ['id' => 'c-own', 'task_name' => 'backup', 'command' => 'php artisan x', 'minute' => '0', 'hour' => '3', 'day_of_month' => '*', 'month' => '*', 'day_of_week' => '*', 'enabled' => true, 'domain_id' => 'dom-own'];
$FOREIGN = ['id' => 'c-foreign', 'task_name' => 'evil', 'command' => 'x', 'enabled' => true, 'domain_id' => 'dom-foreign'];

it('offers the cron feature', function () {
    [$u, $s] = crService(crServer());
    expect((new PanelicaModule)->hostingFeatures($s))->toContain('cron');
});

it('lists only cron jobs on the account\'s own domains', function () use ($JOB, $FOREIGN) {
    fakeCronApi(true, 5, [$JOB, $FOREIGN]);
    [$u, $s] = crService(crServer());
    $list = (new PanelicaModule)->cronJobs($s);
    expect($list)->toHaveCount(1)->and($list[0]['id'])->toBe('c-own')->and($list[0]['domain'])->toBe('example.com');
});

it('reports the plan policy and blocks when cron is disabled', function () {
    fakeCronApi(false, 0, []);
    [$u, $s] = crService(crServer());
    $p = (new PanelicaModule)->cronPolicy($s);
    expect($p['enabled'])->toBeFalse()->and($p['can_create'])->toBeFalse();
});

it('refuses to create when the plan does not include cron — no request sent', function () {
    fakeCronApi(false, 0, []);
    [$u, $s] = crService(crServer());
    $r = (new PanelicaModule)->createCronJob($s, 'dom-own', 't', 'echo hi');
    expect($r['success'])->toBeFalse();
    Http::assertNotSent(fn ($rq) => $rq->method() === 'POST' && str_contains($rq->url(), '/cron-jobs'));
});

it('refuses to create when the plan limit is reached', function () use ($JOB) {
    fakeCronApi(true, 1, [$JOB]);
    [$u, $s] = crService(crServer());
    $r = (new PanelicaModule)->createCronJob($s, 'dom-own', 't', 'echo hi');
    expect($r['success'])->toBeFalse();
    Http::assertNotSent(fn ($rq) => $rq->method() === 'POST' && str_contains($rq->url(), '/cron-jobs'));
});

it('refuses a cron job on a domain the account does not own', function () {
    fakeCronApi(true, 5, []);
    [$u, $s] = crService(crServer());
    $r = (new PanelicaModule)->createCronJob($s, 'dom-foreign', 't', 'echo hi');
    expect($r['success'])->toBeFalse();
    Http::assertNotSent(fn ($rq) => $rq->method() === 'POST');
});

it('creates a cron job on an owned domain with the given schedule', function () {
    fakeCronApi(true, 5, []);
    [$u, $s] = crService(crServer());
    $r = (new PanelicaModule)->createCronJob($s, 'dom-own', 'nightly', 'php artisan backup', ['minute' => '0', 'hour' => '2', 'day_of_month' => '*', 'month' => '*', 'day_of_week' => '*']);
    expect($r['success'])->toBeTrue();
    Http::assertSent(fn ($rq) => $rq->method() === 'POST' && str_contains($rq->url(), '/v1/cron-jobs')
        && ($rq->data()['domain_id'] ?? null) === 'dom-own' && ($rq->data()['hour'] ?? null) === '2');
});

it('fences toggle/run/delete to the account\'s own cron jobs', function () use ($JOB) {
    fakeCronApi(true, 5, [$JOB]);
    [$u, $s] = crService(crServer());
    $mod = new PanelicaModule;
    expect($mod->toggleCronJob($s, 'c-own')['success'])->toBeTrue()
        ->and($mod->toggleCronJob($s, 'c-foreign')['success'])->toBeFalse()
        ->and($mod->runCronJob($s, 'c-foreign')['success'])->toBeFalse()
        ->and($mod->deleteCronJob($s, 'c-foreign')['success'])->toBeFalse();
});

it('returns run output for an owned job', function () use ($JOB) {
    fakeCronApi(true, 5, [$JOB]);
    [$u, $s] = crService(crServer());
    $r = (new PanelicaModule)->runCronJob($s, 'c-own');
    expect($r['success'])->toBeTrue()->and($r['data']['output'])->toBe('ran-ok');
});

it('shows the cron tab and forbids other clients', function () use ($JOB) {
    fakeCronApi(true, 5, [$JOB]);
    [$owner, $s] = crService(crServer());
    $this->actingAs($owner)->get(route('client.services.cron', $s))->assertOk()->assertSee('backup');

    $intruder = User::factory()->create();
    $intruder->clients()->attach(Client::factory()->create()->id);
    $this->actingAs($intruder)->get(route('client.services.cron', $s))->assertForbidden();
    $this->actingAs($intruder)->post(route('client.services.cron.store', $s), ['domain_id' => 'dom-own', 'task_name' => 'x', 'command' => 'y'])->assertForbidden();
    $this->actingAs($intruder)->post(route('client.services.cron.destroy', $s), ['cron_id' => 'c-own'])->assertForbidden();
});
