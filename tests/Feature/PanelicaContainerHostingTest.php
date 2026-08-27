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

it('answers a slow install with a sentence, not a server error', function () use ($CATALOGUE) {
    Http::fake(function ($request) use ($CATALOGUE) {
        $url = $request->url();
        if (str_contains($url, '/deploy')) {
            throw new Illuminate\Http\Client\ConnectionException('cURL error 28: Operation timed out');
        }
        if (str_contains($url, '/docker/templates') && $request->method() === 'GET') {
            return Http::response(['data' => ['templates' => $CATALOGUE]], 200);
        }
        if (preg_match('#/v1/accounts/[^/?]+$#', parse_url($url, PHP_URL_PATH) ?? '')) {
            return Http::response(['data' => ['id' => 'acct-1', 'plan_id' => 'plan-1']], 200);
        }
        if (str_contains($url, '/v1/plans')) {
            return Http::response(['data' => [['id' => 'plan-1', 'max_containers' => 5]]], 200);
        }

        return Http::response(['data' => ['containers' => []]], 200);
    });
    [$u, $s] = ctService(ctServer());

    // A multi-container app pulls gigabytes of images. The request used to time
    // out at thirty seconds and throw, so the customer got "Server Error" while
    // the panel was still building their app.
    $r = (new PanelicaModule)->deployContainer($s, 'wordpress');
    expect($r['success'])->toBeFalse()
        ->and($r['message'])->toContain('still running');
});

/*
 * Serving an app on the customer's own domain.
 *
 * Installing was only half the job: an app nobody can reach on their own
 * address is not what they bought. Both sides of the link are fenced here and
 * again by the panel - our key is operator-scoped and could otherwise point
 * anybody's domain at anybody's app.
 */

function fakeLinkApi(array $containers, array $domains, array $linked = [], bool $linkOk = true): void
{
    Http::fake(function ($request) use ($containers, $domains, $linked, $linkOk) {
        $url = $request->url();
        $m = $request->method();
        if (str_contains($url, '/docker/domains/linked')) {
            return Http::response(['data' => $linked], 200);
        }
        if (str_contains($url, '/docker/domains/link') && $m === 'POST') {
            return $linkOk
                ? Http::response(['data' => ['domain' => 'own.test']], 200)
                : Http::response(['message' => 'apiErrors.docker.containerNotRunning'], 400);
        }
        if (str_contains($url, '/docker/domains/unlink')) {
            return Http::response(['data' => []], 200);
        }
        if (str_contains($url, '/accounts/') && str_contains($url, '/domains')) {
            return Http::response(['data' => $domains], 200);
        }
        if (str_contains($url, '/docker/containers') && $m === 'GET') {
            return Http::response(['data' => ['containers' => $containers]], 200);
        }

        return Http::response(['data' => []], 200);
    });
}

it('points one of the account\'s domains at one of its apps', function () use ($MINE) {
    fakeLinkApi([$MINE], [['id' => 'dom-own', 'domain_name' => 'own.test']]);
    [$u, $s] = ctService(ctServer());

    expect((new PanelicaModule)->linkContainerDomain($s, 'c-mine', 'dom-own')['success'])->toBeTrue();
    Http::assertSent(fn ($rq) => str_contains($rq->url(), '/docker/domains/link')
        && ($rq->data()['owner_user_id'] ?? null) === 'acct-1'
        && ($rq->data()['container_id'] ?? null) === 'c-mine');
});

it('refuses to point a domain at somebody else\'s app', function () use ($MINE, $THEIRS) {
    fakeLinkApi([$MINE, $THEIRS], [['id' => 'dom-own', 'domain_name' => 'own.test']]);
    [$u, $s] = ctService(ctServer());

    expect((new PanelicaModule)->linkContainerDomain($s, 'c-theirs', 'dom-own')['success'])->toBeFalse();
    Http::assertNotSent(fn ($rq) => str_contains($rq->url(), '/docker/domains/link') && $rq->method() === 'POST');
});

it('refuses to point somebody else\'s domain at an app', function () use ($MINE) {
    fakeLinkApi([$MINE], [['id' => 'dom-own', 'domain_name' => 'own.test']]);
    [$u, $s] = ctService(ctServer());

    expect((new PanelicaModule)->linkContainerDomain($s, 'c-mine', 'dom-someone-else')['success'])->toBeFalse();
    Http::assertNotSent(fn ($rq) => str_contains($rq->url(), '/docker/domains/link') && $rq->method() === 'POST');
});

it('explains that an app has to be running before a domain can point at it', function () use ($MINE) {
    fakeLinkApi([$MINE], [['id' => 'dom-own', 'domain_name' => 'own.test']], [], linkOk: false);
    [$u, $s] = ctService(ctServer());

    // The panel refuses this; the customer needs a sentence, not an error code.
    expect((new PanelicaModule)->linkContainerDomain($s, 'c-mine', 'dom-own')['message'])
        ->toContain('Start the app first');
});

it('lists only this account\'s domain links', function () use ($MINE) {
    fakeLinkApi([$MINE], [['id' => 'dom-own', 'domain_name' => 'own.test']], [
        ['domain_id' => 'dom-own', 'container_id' => 'c-mine', 'container_name' => 'acct1-wordpress'],
        ['domain_id' => 'dom-someone-else', 'container_id' => 'c-theirs', 'container_name' => 'other'],
    ]);
    [$u, $s] = ctService(ctServer());

    $links = (new PanelicaModule)->containerDomainLinks($s);
    expect($links)->toHaveKey('dom-own')->not->toHaveKey('dom-someone-else');
});

it('offers the domain controls on the apps page', function () use ($MINE) {
    fakeLinkApi([$MINE], [['id' => 'dom-own', 'domain_name' => 'own.test']]);
    [$owner, $s] = ctService(ctServer());

    $this->actingAs($owner)->get(route('client.services.containers', $s))
        ->assertOk()->assertSee('own.test');
});

it('keeps the domain routes away from other clients', function () use ($MINE) {
    fakeLinkApi([$MINE], [['id' => 'dom-own', 'domain_name' => 'own.test']]);
    [$owner, $s] = ctService(ctServer());

    $intruder = User::factory()->create();
    $intruder->clients()->attach(Client::factory()->create()->id);
    $this->actingAs($intruder)->post(route('client.services.containers.link', $s), [
        'container_id' => 'c-mine', 'domain_id' => 'dom-own',
    ])->assertForbidden();
    $this->actingAs($intruder)->post(route('client.services.containers.unlink', $s), ['domain_id' => 'dom-own'])->assertForbidden();
});

/*
 * How the customer reaches what they installed.
 *
 * The panel reports the address and any generated login exactly once, in the
 * deploy response. Nothing asked for it again, so a customer ended up with a
 * running app and no idea what to open or what password it had made.
 */

it('keeps the address and login the panel reports at install time', function () use ($CATALOGUE) {
    Http::fake(function ($request) use ($CATALOGUE) {
        $url = $request->url();
        if (str_contains($url, '/deploy')) {
            return Http::response(['data' => [
                'container_id' => 'ctr-9', 'container_name' => 'acct1-n8n',
                'access_url' => 'http://1.2.3.4:5678',
                'credentials' => ['Site' => 'http://1.2.3.4:5678', 'Password' => 's3cret'],
                'post_install_notes' => 'Open the address and create your owner account.',
            ]], 200);
        }
        if (str_contains($url, '/docker/templates') && $request->method() === 'GET') {
            return Http::response(['data' => ['templates' => $CATALOGUE]], 200);
        }
        if (preg_match('#/v1/accounts/[^/?]+$#', parse_url($url, PHP_URL_PATH) ?? '')) {
            return Http::response(['data' => ['id' => 'acct-1', 'plan_id' => 'plan-1']], 200);
        }
        if (str_contains($url, '/v1/plans')) {
            return Http::response(['data' => [['id' => 'plan-1', 'max_containers' => 5]]], 200);
        }

        return Http::response(['data' => ['containers' => []]], 200);
    });
    [$u, $s] = ctService(ctServer());

    expect((new PanelicaModule)->deployContainer($s, 'n8n')['success'])->toBeTrue();

    $acc = App\Models\DockerAppCredential::where('service_id', $s->id)->firstOrFail();
    expect($acc->accessUrl())->toBe('http://1.2.3.4:5678')
        ->and($acc->items())->toMatchArray(['Password' => 's3cret'])
        ->and($acc->notes())->toContain('owner account');
});

it('stores the details encrypted, not as readable columns', function () use ($CATALOGUE) {
    App\Models\DockerAppCredential::create([
        'service_id' => ctService(ctServer())[1]->id,
        'container_id' => 'ctr-1', 'container_name' => 'x', 'slug' => 'n8n',
        'payload' => ['credentials' => ['Password' => 'plaintext-secret']],
    ]);

    // A first-login password is a credential; it must not sit in the clear.
    $raw = DB::table('docker_app_credentials')->value('payload');
    expect($raw)->not->toContain('plaintext-secret');
});

it('does not fail an install when the details cannot be recorded', function () use ($CATALOGUE) {
    fakeContainerApi(5, [], $CATALOGUE);
    [$u, $s] = ctService(ctServer());

    // fakeContainerApi returns no access fields at all; the install must still
    // report success rather than dying over a missing note.
    expect((new PanelicaModule)->deployContainer($s, 'wordpress')['success'])->toBeTrue();
});

it('shows the connection details on the apps page', function () use ($MINE) {
    fakeContainerApi(5, [$MINE]);
    [$owner, $s] = ctService(ctServer());
    App\Models\DockerAppCredential::create([
        'service_id' => $s->id, 'container_id' => 'c-mine', 'container_name' => 'acct1-wordpress',
        'slug' => 'wordpress',
        'payload' => ['access_url' => 'http://5.6.7.8:8080', 'credentials' => ['Admin login' => 'http://5.6.7.8:8080/wp-admin']],
    ]);

    $this->actingAs($owner)->get(route('client.services.containers', $s))
        ->assertOk()->assertSee('http://5.6.7.8:8080/wp-admin');
});

/*
 * Reading what the panel already knows about a running container.
 *
 * Everything below was measured against a live panel first: the ports arrive as
 * host_port/container_port, and the inspect response carries the environment the
 * image was started with - which is where an app's generated admin password
 * lives. PNLCS was reading neither, so a customer who installed WordPress saw an
 * empty Ports column and no login anywhere.
 */

/** A fake that answers the container list and the per-container inspect apart. */
function fakeInspectApi(array $listed, array $inspect): void
{
    Http::fake(function ($request) use ($listed, $inspect) {
        $url = $request->url();
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        if (preg_match('#/v1/accounts/[^/?]+$#', $path) && $request->method() === 'GET') {
            return Http::response(['data' => ['id' => 'acct-1', 'plan_id' => 'plan-1']], 200);
        }
        if (str_contains($url, '/v1/plans')) {
            return Http::response(['data' => [['id' => 'plan-1', 'max_containers' => 5]]], 200);
        }
        if (preg_match('#/v1/docker/containers/([^/?]+)$#', $path, $m) && $request->method() === 'GET') {
            return Http::response(['data' => $inspect[$m[1]] ?? []], 200);
        }
        if (str_contains($url, '/docker/containers') && $request->method() === 'GET') {
            return Http::response(['data' => ['containers' => $listed]], 200);
        }

        return Http::response(['data' => []], 200);
    });
}

$WP = [
    'id' => 'c-wp', 'name' => '/acct1-wp', 'image' => 'wordpress', 'state' => 'running',
    'labels' => ['panelica.user_id' => 'acct-1', 'panelica.template' => 'wordpress'],
    'ports' => [
        ['host_port' => '8092', 'container_port' => '80', 'protocol' => 'tcp'],
        ['host_port' => '8456', 'container_port' => '443', 'protocol' => 'tcp'],
        ['host_port' => '8456', 'container_port' => '443', 'protocol' => 'udp'],
        ['host_port' => '', 'container_port' => '7080', 'protocol' => 'tcp'],
    ],
];

$WP_INSPECT = ['c-wp' => [
    'ports' => $WP['ports'],
    'env' => [
        'WP_ADMIN_USER=admin', 'WP_ADMIN_PASSWORD=s3cret-admin', 'DB_PASSWORD=s3cret-db',
        'DB_HOST=mysql', 'PATH=/usr/bin', 'MARIADB_VERSION=11.8', 'DEBIAN_FRONTEND=noninteractive',
        'LS_FD=/usr/local/lsws',
    ],
    'mounts' => [['type' => 'bind', 'source' => '/home/acct1/docker/wp_html', 'destination' => '/var/www', 'rw' => true]],
]];

it('shows the published port the panel reports', function () use ($WP, $WP_INSPECT) {
    fakeInspectApi([$WP], $WP_INSPECT);
    [$owner, $s] = ctService(ctServer());

    $rows = (new PanelicaModule)->containers($s);

    // Both spellings of one mapping collapse to one line, and a port that is not
    // published outside the container is not offered as if it were reachable.
    expect($rows[0]['ports'])->toBe(['8092 → 80', '8456 → 443']);
});

it('reads the login out of a container it did not install', function () use ($WP, $WP_INSPECT) {
    fakeInspectApi([$WP], $WP_INSPECT);
    [$owner, $s] = ctService(ctServer());

    $this->actingAs($owner)->get(route('client.services.containers', $s))
        ->assertOk()
        ->assertSee('s3cret-admin')              // the password the image generated
        ->assertSee('/home/acct1/docker/wp_html')  // where its data lives
        ->assertDontSee('DEBIAN_FRONTEND')       // plumbing, not a credential
        ->assertDontSee('MARIADB_VERSION');
});

it('offers the web port as the address, not whatever else is published', function () use ($WP, $WP_INSPECT) {
    fakeInspectApi([$WP], $WP_INSPECT);
    [$owner, $s] = ctService(ctServer());
    $c = (new PanelicaModule)->containers($s);

    $live = (new PanelicaModule)->liveContainerAccess($s, $c);

    expect($live['c-wp']['access_url'])->toBe('http://panel.test:8092');
});

it('keeps what the panel said at install time over what it says now', function () use ($WP, $WP_INSPECT) {
    fakeInspectApi([$WP], $WP_INSPECT);
    [$owner, $s] = ctService(ctServer());
    App\Models\DockerAppCredential::create([
        'service_id' => $s->id, 'container_id' => 'c-wp', 'container_name' => 'acct1-wp', 'slug' => 'wordpress',
        'payload' => ['access_url' => 'https://shop.example.com', 'credentials' => ['DB_PASSWORD' => 'written-at-install']],
    ]);

    $this->actingAs($owner)->get(route('client.services.containers', $s))
        ->assertOk()
        ->assertSee('https://shop.example.com')
        ->assertSee('written-at-install')
        ->assertDontSee('s3cret-db');
});

it('draws a helper container with its own logo, not the app it belongs to', function () {
    $map = ['wordpress' => '/img/apps/wordpress.svg', 'mariadb' => '/img/apps/mariadb.svg'];

    // Every container of a multi-container app carries the same template label.
    expect(App\Models\DockerApp::forContainer($map, 'mariadb:11.8', 'wordpress'))->toBe('/img/apps/mariadb.svg')
        ->and(App\Models\DockerApp::forContainer($map, 'panelica/openlitespeed-wordpress:latest', 'wordpress'))->toBe('/img/apps/wordpress.svg')
        ->and(App\Models\DockerApp::forContainer($map, 'something/unknown:1', 'unknown'))->toBeNull();
});

/*
 * Getting to a shell.
 *
 * A container's terminal lives in the hosting panel - the panel has one per
 * container and the hosting account is allowed to use it. The apps page sends
 * the customer there, and names the screen it wants rather than a path, because
 * a path from the query string would be an open redirect into a control panel.
 */

it('sends the customer to the panel screen that holds the terminal', function () {
    Http::fake(function ($request) {
        if (str_contains($request->url(), 'sso-login')) {
            return Http::response(['data' => ['url' => 'https://panel.test:8443/auto-login?token=abc']], 200);
        }

        return Http::response(['data' => []], 200);
    });
    [$owner, $s] = ctService(ctServer());

    $this->actingAs($owner)->get(route('client.services.login', ['service' => $s, 'to' => 'docker']))
        ->assertRedirect('https://panel.test:8443/auto-login?token=abc&redirect=%2Fdocker%2Fmanager');
});

it('refuses to forward the customer to an address of somebody else\'s choosing', function () {
    Http::fake(function ($request) {
        if (str_contains($request->url(), 'sso-login')) {
            return Http::response(['data' => ['url' => 'https://panel.test:8443/auto-login?token=abc']], 200);
        }

        return Http::response(['data' => []], 200);
    });
    [$owner, $s] = ctService(ctServer());

    // An unknown intent is dropped, not passed through.
    $this->actingAs($owner)->get(route('client.services.login', ['service' => $s, 'to' => 'https://evil.example/steal']))
        ->assertRedirect('https://panel.test:8443/auto-login?token=abc');
});

it('does not keep a password for an app that has been removed', function () {
    fakeContainerApi(5, [['id' => 'c-gone', 'name' => '/acct1-x', 'image' => 'nginx', 'state' => 'running',
        'labels' => ['panelica.user_id' => 'acct-1']]]);
    [$owner, $s] = ctService(ctServer());
    App\Models\DockerAppCredential::create([
        'service_id' => $s->id, 'container_id' => 'c-gone', 'container_name' => 'acct1-x', 'slug' => 'nginx',
        'payload' => ['credentials' => ['Password' => 'still-here']],
    ]);

    expect((new PanelicaModule)->deleteContainer($s, 'c-gone')['success'])->toBeTrue()
        ->and(App\Models\DockerAppCredential::where('container_id', 'c-gone')->count())->toBe(0);
});

/*
 * A shell for one container.
 *
 * A plain OS template runs `sleep infinity` and ships no SSH server, so the only
 * way into it is the panel's own terminal. The card links straight at it, as the
 * image's user or as root, and the container id is checked against the account
 * first - the billing key is operator-scoped, so an id in a query string proves
 * nothing by itself.
 */

function fakeSsoApi(array $containers): void
{
    Http::fake(function ($request) use ($containers) {
        $url = $request->url();
        if (str_contains($url, 'sso-login')) {
            return Http::response(['data' => ['url' => 'https://panel.test:8443/auto-login?token=abc']], 200);
        }
        if (preg_match('#/v1/accounts/[^/?]+$#', parse_url($url, PHP_URL_PATH) ?? '') && $request->method() === 'GET') {
            return Http::response(['data' => ['id' => 'acct-1', 'plan_id' => 'plan-1']], 200);
        }
        if (str_contains($url, '/docker/containers') && $request->method() === 'GET') {
            return Http::response(['data' => ['containers' => $containers]], 200);
        }

        return Http::response(['data' => []], 200);
    });
}

$OWNED = ['id' => 'c-mine', 'name' => '/acct1-ubuntu', 'image' => 'ubuntu:24.04', 'state' => 'running',
    'labels' => ['panelica.user_id' => 'acct-1', 'panelica.template' => 'ubuntu-2404']];

it('opens the panel on that container as the image user', function () use ($OWNED) {
    fakeSsoApi([$OWNED]);
    [$owner, $s] = ctService(ctServer());

    $this->actingAs($owner)->get(route('client.services.login',
        ['service' => $s, 'to' => 'terminal', 'container' => 'c-mine']))
        ->assertRedirect('https://panel.test:8443/auto-login?token=abc&redirect=%2Fdocker%2Fmanager%3Fterminal%3Dc-mine');
});

it('opens the same shell as root when asked', function () use ($OWNED) {
    fakeSsoApi([$OWNED]);
    [$owner, $s] = ctService(ctServer());

    $this->actingAs($owner)->get(route('client.services.login',
        ['service' => $s, 'to' => 'terminal', 'container' => 'c-mine', 'user' => 'root']))
        ->assertRedirect('https://panel.test:8443/auto-login?token=abc&redirect=%2Fdocker%2Fmanager%3Fterminal%3Dc-mine%26user%3Droot');
});

it('refuses a shell into a container the account does not own', function () use ($OWNED) {
    fakeSsoApi([$OWNED]);
    [$owner, $s] = ctService(ctServer());

    $this->actingAs($owner)->get(route('client.services.login',
        ['service' => $s, 'to' => 'terminal', 'container' => 'c-somebody-else']))
        ->assertSessionHas('error');
});

it('does not carry a user of the caller choosing into the shell', function () use ($OWNED) {
    fakeSsoApi([$OWNED]);
    [$owner, $s] = ctService(ctServer());

    // Anything that is not exactly "root" falls back to the image's own user.
    $this->actingAs($owner)->get(route('client.services.login',
        ['service' => $s, 'to' => 'terminal', 'container' => 'c-mine', 'user' => '0:0 && whoami']))
        ->assertRedirect('https://panel.test:8443/auto-login?token=abc&redirect=%2Fdocker%2Fmanager%3Fterminal%3Dc-mine');
});

it('offers a shell even for an app with no credentials to show', function () use ($OWNED) {
    fakeSsoApi([$OWNED]);
    [$owner, $s] = ctService(ctServer());

    // A bare ubuntu has no address and no login - and is the one that needs the
    // shell most.
    $this->actingAs($owner)->get(route('client.services.containers', $s))
        ->assertOk()->assertSee('to=terminal', false);
});

/*
 * What the apps page does when the panel is only half there.
 *
 * These are the quiet paths: a cap that stops asking after twelve apps, a
 * container the panel cannot answer for, and a volume that is not a folder the
 * customer can open. None of them announce themselves, which is exactly why
 * they are worth pinning - the failure mode is a page that looks fine and is
 * missing something.
 */

function ctContainer(string $id, array $overrides = []): array
{
    return array_merge([
        'id' => $id,
        'name' => '/acct1-'.$id,
        'image' => 'wordpress',
        'state' => 'running',
        'labels' => ['panelica.user_id' => 'acct-1', 'panelica.template' => 'wordpress'],
        'ports' => [['host_port' => '8092', 'container_port' => '80', 'protocol' => 'tcp']],
    ], $overrides);
}

it('stops asking the panel for details after twelve apps', function () {
    $listed = [];
    $inspect = [];
    for ($i = 1; $i <= 15; $i++) {
        $id = 'c-'.$i;
        $listed[] = ctContainer($id);
        $inspect[$id] = [
            'ports' => [['host_port' => (string) (9000 + $i), 'container_port' => '80', 'protocol' => 'tcp']],
            'env' => [], 'mounts' => [],
        ];
    }
    fakeInspectApi($listed, $inspect);
    [$owner, $s] = ctService(ctServer());
    $rows = (new PanelicaModule)->containers($s);

    $live = (new PanelicaModule)->liveContainerAccess($s, $rows);

    // Fifteen apps, twelve answers: the page does not make fifteen round trips
    // to the panel to draw one screen, and the twelve it does ask about are the
    // first twelve it lists - not an arbitrary dozen.
    $firstTwelve = array_column(array_slice($rows, 0, 12), 'id');

    expect($live)->toHaveCount(12)
        ->and(array_keys($live))->toBe($firstTwelve);
});

it('draws the rest of the page when one container cannot be reached', function () {
    $listed = [ctContainer('c-ok'), ctContainer('c-broken')];
    Http::fake(function ($request) use ($listed) {
        $path = parse_url($request->url(), PHP_URL_PATH) ?? '';
        if (preg_match('#/v1/accounts/[^/?]+$#', $path) && $request->method() === 'GET') {
            return Http::response(['data' => ['id' => 'acct-1', 'plan_id' => 'plan-1']], 200);
        }
        if (str_contains($request->url(), '/v1/plans')) {
            return Http::response(['data' => [['id' => 'plan-1', 'max_containers' => 5]]], 200);
        }
        if (str_contains($path, '/v1/docker/containers/c-broken')) {
            return Http::response(['message' => 'container is gone'], 500);
        }
        if (str_contains($path, '/v1/docker/containers/c-ok')) {
            return Http::response(['data' => [
                'ports' => [['host_port' => '8092', 'container_port' => '80', 'protocol' => 'tcp']],
                'env' => [], 'mounts' => [],
            ]], 200);
        }
        if (str_contains($request->url(), '/docker/containers')) {
            return Http::response(['data' => ['containers' => $listed]], 200);
        }

        return Http::response(['data' => []], 200);
    });
    [$owner, $s] = ctService(ctServer());
    $rows = (new PanelicaModule)->containers($s);

    $live = (new PanelicaModule)->liveContainerAccess($s, $rows);

    // The healthy app keeps its address; the broken one is simply absent rather
    // than taking the whole page down with it.
    expect($live)->toHaveKey('c-ok')
        ->and($live['c-ok']['access_url'])->toBe('http://panel.test:8092')
        ->and($live)->not->toHaveKey('c-broken');
});

it('offers a data folder only when there is one the customer can open', function () {
    $listed = [ctContainer('c-vol')];
    fakeInspectApi($listed, ['c-vol' => [
        'ports' => [], 'env' => [],
        'mounts' => [
            // A docker-managed volume is real storage, but it is not a path in
            // the customer's account, so pointing them at it would be a lie.
            ['type' => 'volume', 'source' => 'wp_data'],
            ['type' => 'bind', 'source' => '/var/run/docker.sock'],
        ],
    ]]);
    [$owner, $s] = ctService(ctServer());
    $rows = (new PanelicaModule)->containers($s);

    $live = (new PanelicaModule)->liveContainerAccess($s, $rows);

    expect($live['c-vol']['data_path'])->toBeNull();
});

// -----------------------------------------------------------------------------
// Stacked apps: one template deploys the app plus its helpers (mysql, redis)
// under a shared panelica.stack label. These pin: the stack/role fields, the
// stack-wide delete and action, the refusal to delete a helper on its own, and
// the mail-me-the-details route.
// -----------------------------------------------------------------------------

$STACK_APP = [
    'id' => 'c-app', 'name' => '/acct1-wp', 'image' => 'panelica/olw:latest',
    'state' => 'running', 'labels' => [
        'panelica.user_id' => 'acct-1', 'panelica.template' => 'openlitespeed-wordpress',
        'panelica.stack' => 'acct1-wp',
    ],
];
$STACK_DB = [
    'id' => 'c-db', 'name' => '/acct1-wp-mysql', 'image' => 'mariadb:11',
    'state' => 'running', 'labels' => [
        'panelica.user_id' => 'acct-1', 'panelica.template' => 'openlitespeed-wordpress',
        'panelica.stack' => 'acct1-wp', 'panelica.template.role' => 'mysql',
    ],
];
$STACK_REDIS = [
    'id' => 'c-redis', 'name' => '/acct1-wp-redis', 'image' => 'redis:alpine',
    'state' => 'running', 'labels' => [
        'panelica.user_id' => 'acct-1', 'panelica.template' => 'openlitespeed-wordpress',
        'panelica.stack' => 'acct1-wp', 'panelica.template.role' => 'redis',
    ],
];

it('reports each container\'s stack and role', function () use ($STACK_APP, $STACK_DB) {
    fakeContainerApi(5, [$STACK_APP, $STACK_DB]);
    [$u, $s] = ctService(ctServer());
    $list = collect((new PanelicaModule)->containers($s))->keyBy('id');

    expect($list['c-app']['stack'])->toBe('acct1-wp')
        ->and($list['c-app']['role'])->toBe('')
        ->and($list['c-db']['role'])->toBe('mysql');
});

it('deletes the whole stack when the app is deleted, app first', function () use ($STACK_APP, $STACK_DB, $STACK_REDIS) {
    fakeContainerApi(5, [$STACK_APP, $STACK_DB, $STACK_REDIS]);
    [$u, $s] = ctService(ctServer());

    expect((new PanelicaModule)->deleteContainer($s, 'c-app')['success'])->toBeTrue();
    foreach (['c-app', 'c-db', 'c-redis'] as $id) {
        Http::assertSent(fn ($rq) => $rq->method() === 'DELETE' && str_contains($rq->url(), '/docker/containers/'.$id));
    }
});

it('refuses to delete a helper on its own — no request sent', function () use ($STACK_APP, $STACK_DB) {
    fakeContainerApi(5, [$STACK_APP, $STACK_DB]);
    [$u, $s] = ctService(ctServer());

    expect((new PanelicaModule)->deleteContainer($s, 'c-db')['success'])->toBeFalse();
    Http::assertNotSent(fn ($rq) => $rq->method() === 'DELETE');
});

it('applies an action to every member of the stack', function () use ($STACK_APP, $STACK_DB, $STACK_REDIS) {
    fakeContainerApi(5, [$STACK_APP, $STACK_DB, $STACK_REDIS]);
    [$u, $s] = ctService(ctServer());

    expect((new PanelicaModule)->containerAction($s, 'c-app', 'stop')['success'])->toBeTrue();
    foreach (['c-app', 'c-db', 'c-redis'] as $id) {
        Http::assertSent(fn ($rq) => $rq->method() === 'POST' && str_contains($rq->url(), '/docker/containers/'.$id.'/stop'));
    }
});

it('mails the connection details to the owning client, and only to them', function () use ($STACK_APP) {
    \Illuminate\Support\Facades\Mail::fake();
    fakeContainerApi(5, [$STACK_APP]);
    [$owner, $s] = ctService(ctServer());
    \App\Models\DockerAppCredential::create([
        'service_id' => $s->id, 'container_id' => 'c-app', 'container_name' => 'acct1-wp',
        'slug' => 'openlitespeed-wordpress',
        'payload' => ['access_url' => 'http://10.0.0.7:8092', 'credentials' => ['WP_ADMIN_PASSWORD' => 's3cret'], 'notes' => 'ready'],
    ]);

    $this->actingAs($owner)
        ->post(route('client.services.containers.email', $s), ['container_id' => 'c-app'])
        ->assertRedirect()->assertSessionHas('success');
    \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\ContainerAccessDetailsMail::class, function ($mail) use ($s) {
        return $mail->hasTo($s->client->email) && $mail->items['WP_ADMIN_PASSWORD'] === 's3cret';
    });

    // Somebody else's session must not be able to mail themselves the secrets.
    [$intruder] = ctService(ctServer());
    $this->actingAs($intruder)
        ->post(route('client.services.containers.email', $s), ['container_id' => 'c-app'])
        ->assertForbidden();
    \Illuminate\Support\Facades\Mail::assertSentCount(1);
});
