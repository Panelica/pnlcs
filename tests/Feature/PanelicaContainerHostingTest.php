<?php

use App\Models\Client;
use App\Models\Product;
use App\Models\Server;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Modules\Servers\Panelica\PanelicaModule;

/*
 * Containers: Docker apps the customer runs on their own hosting. The panel puts
 * each one in the account's cgroup slice and strips privileged flags on the
 * external path, so the risk left for billing to handle is showing or touching
 * somebody else's container — the PNLCS key is ROOT-scoped and the panel's list
 * returns every container on the host.
 *
 * These pin: the ownership-label fence, the plan gate, the catalogue gate on
 * install, action/delete ownership, and the controller's 403s.
 */

function ctServer(): Server
{
    return Server::create([
        'name' => 'Panel', 'hostname' => 'panel.test', 'ip_address' => '10.0.0.7',
        'type' => 'panelica', 'username' => 'u', 'password' => 'pk', 'access_hash' => 'sk',
        'port' => 8443, 'active' => true,
    ]);
}

function ctService(Server $server): array
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

/** @param array $containers raw panel list; @param array $templates catalogue */
function fakeContainerApi(int $maxContainers, array $containers = [], array $templates = []): void
{
    Http::fake(function ($request) use ($maxContainers, $containers, $templates) {
        $url = $request->url();
        $m = $request->method();
        if (str_contains($url, '/accounts/') && str_contains($url, '/domains')) {
            return Http::response(['data' => [['id' => 'dom-own', 'domain_name' => 'example.com']]], 200);
        }
        if (preg_match('#/v1/accounts/[^/?]+$#', parse_url($url, PHP_URL_PATH) ?? '') && $m === 'GET') {
            return Http::response(['data' => ['id' => 'acct-1', 'plan_id' => 'plan-1']], 200);
        }
        if (str_contains($url, '/v1/plans') && $m === 'GET') {
            return Http::response(['data' => [['id' => 'plan-1', 'max_containers' => $maxContainers]]], 200);
        }
        if (str_contains($url, '/docker/templates') && $m === 'GET') {
            return Http::response(['data' => ['templates' => $templates]], 200);
        }
        if (str_contains($url, '/docker/templates/') && $m === 'POST') {
            return Http::response(['data' => ['container_id' => 'new']], 200);
        }
        if (str_contains($url, '/docker/containers') && $m === 'GET') {
            return Http::response(['data' => ['containers' => $containers]], 200);
        }
        if (str_contains($url, '/docker/containers/') && $m === 'POST') {
            return Http::response(['status' => 'success'], 200);
        }
        if (str_contains($url, '/docker/containers/') && $m === 'DELETE') {
            return Http::response(['status' => 'success'], 200);
        }

        return Http::response(['data' => []], 200);
    });
}

$MINE = [
    'id' => 'c-mine', 'name' => '/acct1-wordpress', 'image' => 'wordpress:latest',
    'state' => 'running', 'status' => 'Up 2 hours', 'cpu_percent' => 1.5,
    'mem_usage' => 104857600, 'mem_limit' => 536870912, 'ports' => [],
    'labels' => ['panelica.user_id' => 'acct-1', 'panelica.template' => 'wordpress'],
];
$THEIRS = [
    'id' => 'c-theirs', 'name' => '/other-redis', 'image' => 'redis:7',
    'state' => 'running', 'labels' => ['panelica.user_id' => 'acct-999'],
];
$UNLABELLED = [
    'id' => 'c-system', 'name' => '/panelica-internal', 'image' => 'nginx',
    'state' => 'running', 'labels' => [],
];
$CATALOGUE = [
    ['slug' => 'wordpress', 'name' => 'WordPress', 'description' => 'Blog', 'logo_url' => '', 'categories' => ['cms']],
    ['slug' => 'n8n', 'name' => 'n8n', 'description' => 'Automation', 'logo_url' => '', 'categories' => ['automation']],
];

it('offers the containers feature', function () {
    [$u, $s] = ctService(ctServer());
    expect((new PanelicaModule)->hostingFeatures($s))->toContain('containers');
});

it('shows only containers labelled for this account', function () use ($MINE, $THEIRS, $UNLABELLED) {
    fakeContainerApi(5, [$MINE, $THEIRS, $UNLABELLED]);
    [$u, $s] = ctService(ctServer());
    $list = (new PanelicaModule)->containers($s);

    // A ROOT-scoped key sees every container on the host: another account's and
    // the panel's own unlabelled ones must never surface here.
    expect($list)->toHaveCount(1)
        ->and($list[0]['id'])->toBe('c-mine')
        ->and($list[0]['name'])->toBe('acct1-wordpress')   // leading slash trimmed
        ->and($list[0]['template'])->toBe('wordpress');
});

it('reports the plan policy', function () use ($MINE) {
    fakeContainerApi(5, [$MINE]);
    [$u, $s] = ctService(ctServer());
    expect((new PanelicaModule)->containerPolicy($s))
        ->toMatchArray(['max' => 5, 'used' => 1, 'can_create' => true, 'enabled' => true]);
});

it('treats a zero container plan as not included', function () use ($MINE) {
    fakeContainerApi(0, []);
    [$u, $s] = ctService(ctServer());
    $p = (new PanelicaModule)->containerPolicy($s);
    expect($p['enabled'])->toBeFalse()->and($p['can_create'])->toBeFalse();
});

it('refuses to install when the plan excludes apps — no request sent', function () {
    fakeContainerApi(0, []);
    [$u, $s] = ctService(ctServer());
    expect((new PanelicaModule)->deployContainer($s, 'wordpress')['success'])->toBeFalse();
    Http::assertNotSent(fn ($rq) => $rq->method() === 'POST' && str_contains($rq->url(), '/deploy'));
});

it('refuses to install past the plan limit', function () use ($MINE, $CATALOGUE) {
    fakeContainerApi(1, [$MINE], $CATALOGUE);
    [$u, $s] = ctService(ctServer());
    expect((new PanelicaModule)->deployContainer($s, 'n8n')['success'])->toBeFalse();
    Http::assertNotSent(fn ($rq) => $rq->method() === 'POST' && str_contains($rq->url(), '/deploy'));
});

it('refuses an app that is not in the plan catalogue', function () use ($CATALOGUE) {
    fakeContainerApi(5, [], $CATALOGUE);
    [$u, $s] = ctService(ctServer());
    // The panel would refuse it too; failing here gives the customer a sentence.
    expect((new PanelicaModule)->deployContainer($s, 'gitlab')['success'])->toBeFalse();
    Http::assertNotSent(fn ($rq) => $rq->method() === 'POST' && str_contains($rq->url(), '/deploy'));
});

it('installs an allowed app as the account owner', function () use ($CATALOGUE) {
    fakeContainerApi(5, [], $CATALOGUE);
    [$u, $s] = ctService(ctServer());
    expect((new PanelicaModule)->deployContainer($s, 'wordpress', 'my-blog')['success'])->toBeTrue();
    Http::assertSent(fn ($rq) => $rq->method() === 'POST'
        && str_contains($rq->url(), '/docker/templates/wordpress/deploy')
        && ($rq->data()['owner_user_id'] ?? null) === 'acct-1'
        && ($rq->data()['container_name'] ?? null) === 'my-blog');
});

it('rejects an unusable container name before calling the panel', function () use ($CATALOGUE) {
    fakeContainerApi(5, [], $CATALOGUE);
    [$u, $s] = ctService(ctServer());
    expect((new PanelicaModule)->deployContainer($s, 'wordpress', 'my blog!')['success'])->toBeFalse();
    Http::assertNotSent(fn ($rq) => $rq->method() === 'POST' && str_contains($rq->url(), '/deploy'));
});

it('fences start/stop/remove to the account\'s own containers', function () use ($MINE, $THEIRS) {
    fakeContainerApi(5, [$MINE, $THEIRS]);
    [$u, $s] = ctService(ctServer());
    $mod = new PanelicaModule;

    expect($mod->containerAction($s, 'c-mine', 'restart')['success'])->toBeTrue()
        ->and($mod->containerAction($s, 'c-theirs', 'stop')['success'])->toBeFalse()
        ->and($mod->deleteContainer($s, 'c-theirs')['success'])->toBeFalse()
        ->and($mod->containerAction($s, 'c-mine', 'exec')['success'])->toBeFalse(); // unsupported action

    Http::assertNotSent(fn ($rq) => str_contains($rq->url(), 'c-theirs'));
});

/*
 * Choosing an app: 98 of them across nine sections, each with a cost the plan
 * may or may not be able to pay.
 */

it('carries what an app needs so the page can price it against the plan', function () {
    fakeContainerApi(5, [], [[
        'slug' => 'gitlab', 'name' => 'GitLab', 'description' => 'Git', 'logo_url' => '',
        'categories' => ['git'], 'min_memory_mb' => 4096, 'min_cpu_percent' => 200, 'is_popular' => true,
    ]]);
    [$u, $s] = ctService(ctServer());

    expect((new PanelicaModule)->containerTemplates($s)[0])
        ->toMatchArray(['min_memory_mb' => 4096, 'min_cpu_percent' => 200, 'is_popular' => true]);
});

it('reports the plan ceilings an app will run under', function () {
    Http::fake(function ($request) {
        $url = $request->url();
        if (preg_match('#/v1/accounts/[^/?]+$#', parse_url($url, PHP_URL_PATH) ?? '')) {
            return Http::response(['data' => ['id' => 'acct-1', 'plan_id' => 'plan-1']], 200);
        }
        if (str_contains($url, '/v1/plans')) {
            return Http::response(['data' => [['id' => 'plan-1', 'memory_limit_mb' => 2048, 'cpu_limit_percent' => 150]]], 200);
        }

        return Http::response(['data' => []], 200);
    });
    [$u, $s] = ctService(ctServer());

    expect((new PanelicaModule)->containerResources($s))
        ->toBe(['memory_mb' => 2048, 'cpu_percent' => 150]);
});

it('resolves the plan once however many limits the page asks for', function () use ($MINE) {
    fakeContainerApi(5, [$MINE]);
    [$u, $s] = ctService(ctServer());
    $mod = new PanelicaModule;

    $mod->containerPolicy($s);
    $mod->containerResources($s);

    // Two calls resolve a plan (account, then plans). Asking for the container
    // limit and the CPU/RAM ceilings separately used to cost four.
    Http::assertSentCount(collect(Http::recorded())->count());
    expect(collect(Http::recorded())->filter(fn ($p) => str_contains($p[0]->url(), '/v1/plans'))->count())->toBe(1);
});

it('groups the catalogue into sections and never drops an app', function () {
    fakeContainerApi(5, [], [
        ['slug' => 'wordpress', 'name' => 'WordPress', 'description' => '', 'logo_url' => '', 'categories' => ['cms']],
        ['slug' => 'redis', 'name' => 'Redis', 'description' => '', 'logo_url' => '', 'categories' => ['cache']],
        ['slug' => 'oddity', 'name' => 'Oddity', 'description' => '', 'logo_url' => '', 'categories' => ['not-a-known-tag']],
    ]);
    [$owner, $s] = ctService(ctServer());

    $groups = $this->actingAs($owner)->get(route('client.services.containers', $s))
        ->assertOk()->viewData('groups');

    $keys = array_column($groups, 'key');
    expect($keys)->toContain('websites')->toContain('databases')
        // An unmapped tag must not vanish from the catalogue.
        ->toContain('other');
    expect(collect($groups)->flatMap(fn ($g) => $g['apps'])->pluck('slug')->all())
        ->toHaveCount(3)->toContain('oddity');
});

it('shows the containers tab and forbids other clients', function () use ($MINE, $CATALOGUE) {
    fakeContainerApi(5, [$MINE], $CATALOGUE);
    [$owner, $s] = ctService(ctServer());
    $this->actingAs($owner)->get(route('client.services.containers', $s))->assertOk()->assertSee('acct1-wordpress');

    $intruder = User::factory()->create();
    $intruder->clients()->attach(Client::factory()->create()->id);
    $this->actingAs($intruder)->get(route('client.services.containers', $s))->assertForbidden();
    $this->actingAs($intruder)->post(route('client.services.containers.store', $s), ['slug' => 'wordpress'])->assertForbidden();
    $this->actingAs($intruder)->post(route('client.services.containers.action', $s), ['container_id' => 'c-mine', 'action' => 'stop'])->assertForbidden();
    $this->actingAs($intruder)->post(route('client.services.containers.destroy', $s), ['container_id' => 'c-mine'])->assertForbidden();
});
