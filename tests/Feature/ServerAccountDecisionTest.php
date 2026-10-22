<?php

use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Currency;
use App\Models\ModuleQueue;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\ServerGroup;
use App\Models\Service;
use App\Models\User;
use App\Services\HostedDomainCheck;
use App\Services\NotificationService;
use App\Services\OrderService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/*
 * #212: a service marked Terminated with the status buttons, then deleted,
 * left its account, domain and app running on the Panelica server with
 * nothing billing them; ordering the same domain again stopped in Pending
 * with no word to anyone. Seen in the reporter's screen recording: the
 * status button changes the record only, and Delete trusted the record.
 *
 * Now closing a record whose account is still on its server asks first
 * (terminate it there, the default, or keep it on purpose); the reason a
 * setup did not go through is shown and announced; and a domain already on
 * the servers is refused while the customer orders.
 *
 * The panel is faked at the HTTP level with the answers the Panelica module
 * reads: GET/DELETE /v1/accounts/{id}, GET /v1/domains.
 */

function p212Panel(array $over = []): array
{
    return array_merge([
        'accounts' => ['u-1' => ['app.example.test']],
        'calls' => [],
        'down' => false,
        'refuse_delete' => false,
    ], $over);
}

function p212Fake(array &$panel): void
{
    Http::fake(function (Request $request) use (&$panel) {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);
        $panel['calls'][] = [$request->method(), $path, (string) parse_url($request->url(), PHP_URL_HOST)];

        if ($panel['down']) {
            throw new ConnectionException('cURL error 7: Failed to connect');
        }

        if (preg_match('#^/api/external/v1/accounts/([^/]+)$#', $path, $m)) {
            $id = rawurldecode($m[1]);
            if ($request->method() === 'GET') {
                return isset($panel['accounts'][$id])
                    ? Http::response(['status' => 'success', 'data' => ['id' => $id]], 200)
                    : Http::response(['status' => 'error', 'error' => 'not found'], 404);
            }
            if ($request->method() === 'DELETE') {
                if ($panel['refuse_delete']) {
                    return Http::response(['status' => 'error', 'error' => 'Delete failed', 'details' => 'database is locked'], 500);
                }
                unset($panel['accounts'][$id]);

                return Http::response(['status' => 'success'], 200);
            }
        }

        if ($path === '/api/external/v1/domains' && $request->method() === 'GET') {
            $list = [];
            foreach ($panel['accounts'] as $id => $domains) {
                foreach ($domains as $d) {
                    $list[] = ['id' => 'dom-'.$d, 'domain_name' => $d, 'user_id' => $id];
                }
            }

            return Http::response(['status' => 'success', 'data' => $list], 200);
        }

        return Http::response(['status' => 'error', 'error' => 'unexpected '.$request->method().' '.$path], 404);
    });
}

/** Panel calls of one kind, e.g. p212Calls($panel, 'DELETE'). */
function p212Calls(array $panel, string $method): array
{
    return array_values(array_filter($panel['calls'], fn ($c) => $c[0] === $method));
}

function p212Server(string $name = 'Panelica01', string $ip = '203.0.113.9'): Server
{
    return Server::create([
        'name' => $name, 'hostname' => strtolower($name).'.test', 'ip_address' => $ip,
        'type' => 'panelica', 'username' => 'root', 'password' => 'pk_live_x', 'access_hash' => 'sk_live_x',
        'port' => 8443, 'active' => true,
    ]);
}

function p212Product(array $over = []): Product
{
    return Product::factory()->create(array_merge([
        'group_id' => ProductGroup::factory()->create()->id,
        'type' => 'hosting', 'server_type' => 'panelica', 'hidden' => false, 'retired' => false,
    ], $over));
}

/** The reporter's service: an app product on a domain, set up on the panel. */
function p212Service(?Server $server = null, array $over = []): Service
{
    $server ??= p212Server();

    return Service::factory()->create(array_merge([
        'client_id' => Client::factory()->create()->id,
        'product_id' => p212Product()->id,
        'server_id' => $server->id,
        'status' => 'active',
        'domain' => 'app.example.test',
        'username' => 'app349',
        'module_data' => ['panelica_user_id' => 'u-1', 'panelica_domain_id' => 'dom-app'],
    ], $over));
}

function p212Admin(): Admin
{
    return Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);
}

// --- marking the record Terminated ----------------------------------------------

test('marking a service Terminated while its account is on the panel asks first, and changes nothing until answered', function () {
    Mail::fake();
    $panel = p212Panel();
    p212Fake($panel);
    $service = p212Service();

    $this->actingAs(p212Admin(), 'admin')->from(route('admin.services.show', $service))
        ->put(route('admin.services.status', $service), ['status' => 'terminated'])
        ->assertRedirect(route('admin.services.show', $service))
        ->assertSessionHas('server_decision', ['action' => 'status', 'status' => 'terminated', 'state' => 'live']);

    expect($service->fresh()->status)->toBe('active')
        ->and(p212Calls($panel, 'DELETE'))->toBe([])
        ->and($panel['accounts'])->toHaveKey('u-1');
});

test('the question names the account, the domain and the server, and offers both answers', function () {
    $panel = p212Panel();
    p212Fake($panel);
    $service = p212Service();
    $admin = p212Admin();

    $page = $this->actingAs($admin, 'admin')
        ->withSession(['server_decision' => ['action' => 'status', 'status' => 'terminated', 'state' => 'live']])
        ->get(route('admin.services.show', $service))->assertOk()->getContent();

    expect($page)->toContain(e(__('admin.services.server_decision_title', ['server' => 'Panelica01'])))
        ->and($page)->toContain(e(__('admin.services.server_decision_live', ['account' => 'app349', 'domain' => 'app.example.test', 'server' => 'Panelica01'])))
        ->and($page)->toContain(e(__('admin.services.decision_terminate_status', ['status' => status_label('terminated')])))
        ->and($page)->toContain(e(__('admin.services.decision_keep_status', ['status' => status_label('terminated')])))
        ->and($page)->toMatch('#name="server" value="terminate">\s*<button type="submit" class="btn btn-danger btn-sm" autofocus>#');

    $page = $this->actingAs($admin, 'admin')
        ->withSession(['server_decision' => ['action' => 'delete', 'state' => 'live']])
        ->get(route('admin.services.show', $service))->getContent();
    expect($page)->toContain(e(__('admin.services.decision_terminate_delete')))
        ->and($page)->toContain(e(__('admin.services.decision_keep_delete')))
        ->and($page)->toContain(e(__('admin.services.server_decision_delete_why')));
});

test('the default answer terminates the account on the panel, then marks the record', function () {
    Mail::fake();
    $panel = p212Panel();
    p212Fake($panel);
    $service = p212Service();

    $this->actingAs(p212Admin(), 'admin')
        ->put(route('admin.services.status', $service), ['status' => 'terminated', 'server' => 'terminate'])
        ->assertSessionHas('success', __('admin.services.status_closed_on_server', ['status' => status_label('terminated')]));

    expect(p212Calls($panel, 'DELETE'))->toHaveCount(1)
        ->and($panel['accounts'])->not->toHaveKey('u-1')
        ->and($service->fresh()->status)->toBe('terminated')
        ->and($service->fresh()->termination_date)->not->toBeNull();
});

test('Cancelled, answered with terminate, ends the account and stays Cancelled', function () {
    Mail::fake();
    $panel = p212Panel();
    p212Fake($panel);
    $service = p212Service();

    $this->actingAs(p212Admin(), 'admin')
        ->put(route('admin.services.status', $service), ['status' => 'cancelled', 'server' => 'terminate'])
        ->assertSessionHas('success');

    expect(p212Calls($panel, 'DELETE'))->toHaveCount(1)
        ->and($service->fresh()->status)->toBe('cancelled');
});

test('keeping the account marks the record only, and the activity log says so', function () {
    $panel = p212Panel();
    p212Fake($panel);
    $service = p212Service();

    $this->actingAs(p212Admin(), 'admin')
        ->put(route('admin.services.status', $service), ['status' => 'terminated', 'server' => 'keep'])
        ->assertSessionHas('success', __('admin.services.status_kept_on_server', ['status' => status_label('terminated')]));

    expect(p212Calls($panel, 'DELETE'))->toBe([])
        ->and($panel['accounts'])->toHaveKey('u-1')
        ->and($service->fresh()->status)->toBe('terminated')
        ->and(ActivityLog::where('description', 'like', "Service #{$service->id} marked terminated; its account on the server was kept%")->exists())->toBeTrue();
});

test('a termination the panel refuses changes nothing and asks again', function () {
    $panel = p212Panel(['refuse_delete' => true]);
    p212Fake($panel);
    $service = p212Service();

    $this->actingAs(p212Admin(), 'admin')
        ->put(route('admin.services.status', $service), ['status' => 'terminated', 'server' => 'terminate'])
        ->assertSessionHas('error', fn ($m) => str_contains($m, 'Delete failed'))
        ->assertSessionHas('server_decision');

    expect($service->fresh()->status)->toBe('active')
        ->and(ModuleQueue::where('service_id', $service->id)->exists())->toBeFalse();
});

test('status changes that do not close the record do not ask', function (string $status) {
    $panel = p212Panel();
    p212Fake($panel);
    $service = p212Service();

    $this->actingAs(p212Admin(), 'admin')
        ->put(route('admin.services.status', $service), ['status' => $status])
        ->assertSessionMissing('server_decision')->assertSessionHas('success');

    expect($service->fresh()->status)->toBe($status)
        ->and($panel['calls'])->toBe([]);
})->with(['suspended', 'pending']);

// --- deleting ---------------------------------------------------------------------

test('deleting a service whose account is on the panel asks first; the default terminates it there and deletes', function () {
    Mail::fake();
    $panel = p212Panel();
    p212Fake($panel);
    $service = p212Service(null, ['status' => 'terminated']); // marked with the status button, as in the report
    $admin = p212Admin();

    $this->actingAs($admin, 'admin')->delete(route('admin.services.destroy', $service))
        ->assertSessionHas('server_decision', ['action' => 'delete', 'state' => 'live']);
    expect(Service::find($service->id))->not->toBeNull();

    $this->actingAs($admin, 'admin')->delete(route('admin.services.destroy', $service), ['server' => 'terminate'])
        ->assertRedirect(route('admin.services.index'))
        ->assertSessionHas('success', __('admin.services.terminated_and_deleted'));

    expect(Service::find($service->id))->toBeNull()
        ->and(p212Calls($panel, 'DELETE'))->toHaveCount(1)
        ->and($panel['accounts'])->toBe([])
        // Nothing left on the panel: the domain can be ordered again.
        ->and(HostedDomainCheck::problem(p212Product(), 'app.example.test'))->toBeNull();
});

test('a termination the panel refuses deletes nothing and asks again', function () {
    $panel = p212Panel(['refuse_delete' => true]);
    p212Fake($panel);
    $service = p212Service(null, ['status' => 'terminated']);

    $this->actingAs(p212Admin(), 'admin')->delete(route('admin.services.destroy', $service), ['server' => 'terminate'])
        ->assertSessionHas('error', fn ($m) => str_contains($m, 'Delete failed'))
        ->assertSessionHas('server_decision', ['action' => 'delete', 'state' => 'live']);

    expect(Service::find($service->id))->not->toBeNull();
});

test('deleting in PNLCS only keeps the account; the log says so and the customer cannot order the domain again', function () {
    $panel = p212Panel();
    p212Fake($panel);
    $server = p212Server();
    $service = p212Service($server, ['status' => 'terminated']);

    $this->actingAs(p212Admin(), 'admin')->delete(route('admin.services.destroy', $service), ['server' => 'keep'])
        ->assertSessionHas('success', __('admin.services.deleted_kept_on_server', ['server' => 'Panelica01']));

    expect(Service::find($service->id))->toBeNull()
        ->and(p212Calls($panel, 'DELETE'))->toBe([])
        ->and($panel['accounts'])->toHaveKey('u-1')
        ->and(ActivityLog::where('description', 'like', "Service #{$service->id} (app.example.test) deleted; its account on the server was kept%")->exists())->toBeTrue()
        ->and(HostedDomainCheck::problem(p212Product(), 'app.example.test'))->toBe(__('client.cart.domain_already_hosted', ['domain' => 'app.example.test']));
});

test('an account already gone from the panel is deleted without a question', function () {
    $panel = p212Panel(['accounts' => []]);
    p212Fake($panel);
    $service = p212Service(null, ['status' => 'terminated']);

    $this->actingAs(p212Admin(), 'admin')->delete(route('admin.services.destroy', $service))
        ->assertSessionMissing('server_decision')
        ->assertSessionHas('success', __('admin.messages.service_deleted'));

    expect(Service::find($service->id))->toBeNull()
        ->and(p212Calls($panel, 'DELETE'))->toBe([]);
});

test('a panel that cannot be reached is not taken for a gone account', function () {
    $panel = p212Panel(['down' => true]);
    p212Fake($panel);
    $service = p212Service(null, ['status' => 'terminated']);
    $admin = p212Admin();

    $this->actingAs($admin, 'admin')->delete(route('admin.services.destroy', $service))
        ->assertSessionHas('server_decision', ['action' => 'delete', 'state' => 'unknown']);
    expect(Service::find($service->id))->not->toBeNull();

    $page = $this->actingAs($admin, 'admin')
        ->withSession(['server_decision' => ['action' => 'delete', 'state' => 'unknown']])
        ->get(route('admin.services.show', $service))->getContent();
    expect($page)->toContain(e(__('admin.services.server_decision_unknown', ['server' => 'Panelica01'])));
});

test('a service never set up on a panel is deleted directly, without asking the panel', function () {
    $panel = p212Panel();
    p212Fake($panel);
    $service = p212Service(null, ['status' => 'pending', 'module_data' => null, 'username' => null]);

    $this->actingAs(p212Admin(), 'admin')->delete(route('admin.services.destroy', $service))
        ->assertSessionHas('success', __('admin.messages.service_deleted'));

    expect(Service::find($service->id))->toBeNull()
        ->and($panel['calls'])->toBe([]);
});

/*
 * A service PNLCS did not set up (imported, or added without linking its
 * account) has no account id. Before #212 an active one could not be deleted
 * at all; it must not now be deleted without a question either.
 */
test('an active service not linked to an account, whose domain is on the panel, asks before it is deleted', function () {
    $panel = p212Panel();
    p212Fake($panel);
    $service = p212Service(null, ['module_data' => null]);

    $this->actingAs(p212Admin(), 'admin')->delete(route('admin.services.destroy', $service))
        ->assertSessionHas('server_decision', ['action' => 'delete', 'state' => 'live']);

    expect(Service::find($service->id))->not->toBeNull()
        ->and(p212Calls($panel, 'GET'))->toHaveCount(1)
        ->and(p212Calls($panel, 'GET')[0][1])->toBe('/api/external/v1/domains');
});

test('an active service not linked to an account, whose domain is not on the panel, still asks: PNLCS cannot tell', function () {
    $panel = p212Panel(['accounts' => ['u-9' => ['other.example.test']]]);
    p212Fake($panel);
    $service = p212Service(null, ['module_data' => null]);

    $this->actingAs(p212Admin(), 'admin')->delete(route('admin.services.destroy', $service))
        ->assertSessionHas('server_decision', ['action' => 'delete', 'state' => 'unknown']);

    expect(Service::find($service->id))->not->toBeNull();
});

test('terminating a service not linked to an account changes nothing and asks again; keeping it deletes the record', function () {
    $panel = p212Panel();
    p212Fake($panel);
    $service = p212Service(null, ['module_data' => null]);
    $admin = p212Admin();

    $this->actingAs($admin, 'admin')->delete(route('admin.services.destroy', $service), ['server' => 'terminate'])
        ->assertSessionHas('error')
        ->assertSessionHas('server_decision', ['action' => 'delete', 'state' => 'live']);
    expect(Service::find($service->id))->not->toBeNull()
        ->and(p212Calls($panel, 'DELETE'))->toBe([]);

    $this->actingAs($admin, 'admin')->delete(route('admin.services.destroy', $service), ['server' => 'keep'])
        ->assertSessionHas('success', __('admin.services.deleted_kept_on_server', ['server' => 'Panelica01']));
    expect(Service::find($service->id))->toBeNull()
        ->and($panel['accounts'])->toHaveKey('u-1');
});

test('an active service while the panel cannot be reached says it could not check, not that the account is there', function () {
    $panel = p212Panel(['down' => true]);
    p212Fake($panel);
    $service = p212Service();

    $this->actingAs(p212Admin(), 'admin')->delete(route('admin.services.destroy', $service))
        ->assertSessionHas('server_decision', ['action' => 'delete', 'state' => 'unknown']);
    expect(Service::find($service->id))->not->toBeNull();
});

test('the report, start to end: marked Terminated, deleted, ordered again on the same domain', function () {
    Mail::fake();
    $panel = p212Panel();
    p212Fake($panel);
    $service = p212Service();
    $admin = p212Admin();

    // Manage Status -> Terminated: asked, and the default answer taken.
    $this->actingAs($admin, 'admin')->put(route('admin.services.status', $service), ['status' => 'terminated'])->assertSessionHas('server_decision');
    $this->actingAs($admin, 'admin')->put(route('admin.services.status', $service), ['status' => 'terminated', 'server' => 'terminate']);
    // Delete: nothing is left on the panel, so it is not asked again.
    $this->actingAs($admin, 'admin')->delete(route('admin.services.destroy', $service))->assertSessionMissing('server_decision');

    expect(Service::find($service->id))->toBeNull()
        ->and($panel['accounts'])->toBe([])
        ->and(HostedDomainCheck::problem(p212Product(), 'app.example.test'))->toBeNull();
});

// --- the status buttons say what they do ------------------------------------------------

test('the status buttons say they change the record only, and point at Module Actions', function () {
    $panel = p212Panel();
    p212Fake($panel);
    $service = p212Service();

    $page = $this->actingAs(p212Admin(), 'admin')->get(route('admin.services.show', $service))->assertOk()->getContent();

    expect($page)->toContain(e(__('admin.services.manage_status_hint', ['actions' => __('admin.services.module_actions')])))
        ->and($page)->toContain(__('admin.services.confirm_status', ['status' => status_label('terminated')]))
        ->and($page)->not->toContain('id="server-decision"')
        // Opening the page asks the panel nothing.
        ->and($panel['calls'])->toBe([]);
});

// --- a setup that did not go through ---------------------------------------------------

test('a setup that did not go through shows why on the staff page, and the customer is told it is in hand', function () {
    $panel = p212Panel();
    p212Fake($panel);
    $service = p212Service(null, ['status' => 'pending', 'module_data' => null, 'username' => null]);
    ModuleQueue::create([
        'service_id' => $service->id, 'action' => 'create', 'status' => 'pending', 'attempts' => 1, 'max_attempts' => 5,
        'next_attempt_at' => now()->addMinutes(30), 'last_error' => 'The domain "app.example.test" already exists on this server.',
    ]);

    $page = $this->actingAs(p212Admin(), 'admin')->get(route('admin.services.show', $service))->assertOk()->getContent();
    expect($page)->toContain(e(__('admin.services.setup_pending_title')))
        ->and($page)->toContain(e('The domain "app.example.test" already exists on this server.'));

    $user = User::factory()->create();
    $user->clients()->attach($service->client_id, ['owner' => true]);
    $client = $this->actingAs($user)->get(route('client.services.show', $service))->assertOk()->getContent();
    expect($client)->toContain(e(__('client.services.setup_delayed')))
        ->and($client)->not->toContain('already exists on this server');
});

test('a setup on order placement that fails is announced to staff, worded for an order not yet paid', function () {
    $panel = p212Panel(); // app.example.test is still on the panel
    p212Fake($panel);
    p212Server();
    $product = p212Product(['auto_setup' => 'order', 'pay_type' => 'free']);
    $client = Client::factory()->create(['tax_exempt' => true]);

    $announced = [];
    $this->mock(NotificationService::class)->shouldReceive('dispatch')
        ->andReturnUsing(function ($type, $data) use (&$announced) {
            $announced[] = [$type, $data];
        });

    $order = app(OrderService::class)->processOrder($client, [[
        'type' => 'service', 'product_id' => $product->id, 'domain' => 'app.example.test', 'amount' => 0, 'billing_cycle' => 'Monthly',
    ]], 'banktransfer');

    $service = Service::where('order_id', $order->id)->first();
    expect($service->status)->toBe('pending')
        ->and(collect($announced)->pluck(0)->all())->toContain('service.provision_failed');
    $message = collect($announced)->firstWhere(0, 'service.provision_failed')[1]['message'];
    expect($message)->toContain('The order was placed but the service could not be provisioned')
        ->and($message)->toContain('already exists on this server');
});

// --- ordering a domain that is already on the servers ------------------------------------

function p212CartProduct(array $over = []): Product
{
    $product = p212Product($over);
    Pricing::create([
        'type' => 'product', 'rel_id' => $product->id, 'monthly' => 10,
        'currency_id' => Currency::getDefault()?->id ?? Currency::factory()->create()->id,
    ]);

    return $product;
}

test('a domain already on the server is refused while ordering, with a sentence for the customer', function () {
    $panel = p212Panel();
    p212Fake($panel);
    p212Server();
    $product = p212CartProduct();

    $this->post(route('client.cart.add'), ['product_id' => $product->id, 'billing_cycle' => 'monthly', 'domain' => 'APP.example.test', 'domain_option' => 'own'])
        ->assertSessionHasErrors(['domain' => __('client.cart.domain_already_hosted', ['domain' => 'app.example.test'])]);

    $this->post(route('client.cart.add'), ['product_id' => $product->id, 'billing_cycle' => 'monthly', 'domain' => 'fresh.example.test', 'domain_option' => 'own'])
        ->assertSessionHasNoErrors();
});

test('a server that cannot be asked does not stop the order', function () {
    $panel = p212Panel(['down' => true]);
    p212Fake($panel);
    p212Server();
    $product = p212CartProduct();

    $this->post(route('client.cart.add'), ['product_id' => $product->id, 'billing_cycle' => 'monthly', 'domain' => 'app.example.test', 'domain_option' => 'own'])
        ->assertSessionHasNoErrors();
});

test('with a server group, the group\'s servers are the ones asked', function () {
    $panel = p212Panel();
    p212Fake($panel);
    p212Server('Elsewhere', '203.0.113.10'); // first active server of the type, outside the group
    $inGroup = p212Server('GroupOne', '203.0.113.11');
    $group = ServerGroup::factory()->create();
    $group->servers()->attach($inGroup->id);
    $product = p212CartProduct(['server_group_id' => $group->id]);

    expect(HostedDomainCheck::problem($product, 'app.example.test'))->not->toBeNull()
        ->and(collect(p212Calls($panel, 'GET'))->map(fn ($c) => $c[2].$c[1])->all())->toBe(['203.0.113.11/api/external/v1/domains']);

    // An empty group asks nobody.
    $product->update(['server_group_id' => ServerGroup::factory()->create()->id]);
    $panel['calls'] = [];
    expect(HostedDomainCheck::problem($product->fresh(), 'app.example.test'))->toBeNull()
        ->and($panel['calls'])->toBe([]);
});

test('a product whose module cannot tell is not asked', function () {
    $panel = p212Panel();
    p212Fake($panel);
    $product = Product::factory()->create(['server_type' => 'cpanel']);

    expect(HostedDomainCheck::problem($product, 'app.example.test'))->toBeNull()
        ->and($panel['calls'])->toBe([]);
});
