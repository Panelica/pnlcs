<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductTerm;
use App\Models\Server;
use App\Models\Service;
use App\Services\ServerAccountSync;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

/*
 * Bringing every account on a server into billing at once: the server's list
 * set against the services PNLCS has, and the rest imported and linked.
 */

/**
 * A Plesk agent that knows a few subscriptions, two customers and two plans,
 * and answers a lookup of one subscription with that one alone.
 *
 * @param  array<int, array{name: string, owner: int, status?: int, plan?: string, created?: string}>  $subscriptions
 */
function fakeSyncPlesk(array $subscriptions): void
{
    $plans = ['g-basic' => 'Hosting Basic', 'g-pro' => 'Hosting Pro'];
    $customers = [5 => ['rossi', 'Rossi Srl', 'info@rossi.it'], 6 => ['bianchi', 'Bianchi Spa', 'admin@bianchi.com']];

    Http::preventStrayRequests();
    Http::fake(['https://plesk.test:8443/enterprise/control/agent.php' => function (Request $request) use ($subscriptions, $plans, $customers) {
        $body = $request->body();
        $out = '';

        if (str_contains($body, '<webspace><get>')) {
            $only = preg_match('/<filter><id>(\d+)<\/id><\/filter>/', $body, $m) ? (int) $m[1] : null;
            $out .= '<webspace><get>';
            foreach ($subscriptions as $id => $s) {
                if ($only !== null && $only !== $id) {
                    continue;
                }
                $plan = isset($s['plan']) ? '<subscriptions><subscription><plan><plan-guid>'.$s['plan'].'</plan-guid></plan></subscription></subscriptions>' : '';
                $out .= "<result><status>ok</status><id>{$id}</id><data><gen_info><cr_date>".($s['created'] ?? '2020-03-10')."</cr_date><name>{$s['name']}</name>"
                    ."<owner-id>{$s['owner']}</owner-id><status>".($s['status'] ?? 0)."</status></gen_info>{$plan}</data></result>";
            }
            $out .= '</get></webspace>';
        }
        if (str_contains($body, '<service-plan><get>')) {
            $out .= '<service-plan><get>';
            foreach ($plans as $guid => $name) {
                $out .= "<result><status>ok</status><id>1</id><name>{$name}</name><guid>{$guid}</guid></result>";
            }
            $out .= '</get></service-plan>';
        }
        if (str_contains($body, '<customer><get>')) {
            $out .= '<customer><get>';
            foreach ($customers as $id => [$login, $company, $email]) {
                $out .= "<result><status>ok</status><id>{$id}</id><data><gen_info><login>{$login}</login><cname>{$company}</cname><pname></pname><email>{$email}</email></gen_info></data></result>";
            }
            $out .= '</get></customer><reseller><get></get></reseller>';
        }

        return Http::response('<?xml version="1.0"?><packet>'.$out.'</packet>', 200);
    }]);
}

function syncServer(): Server
{
    return Server::factory()->create([
        'type' => 'plesk', 'hostname' => 'plesk.test', 'ip_address' => '', 'port' => 8443,
        'username' => 'admin', 'password' => 'admin-secret', 'access_hash' => 'sk-123',
    ]);
}

function syncAdmin(array $permissions = []): Admin
{
    $role = $permissions === []
        ? AdminRole::factory()->fullAdmin()->create()
        : AdminRole::factory()->create(['permissions' => $permissions]);

    return Admin::factory()->create(['role_id' => $role->id]);
}

function syncProduct(string $plan): Product
{
    return Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'server_type' => 'plesk',
        'config_options' => ['package_name' => $plan],
    ]);
}

it('lists the accounts with what bills them and guesses the rest', function () {
    $this->travelTo('2026-11-11');
    fakeSyncPlesk([
        11 => ['name' => 'rossi.it', 'owner' => 5, 'plan' => 'g-pro', 'created' => '2019-02-20'],
        12 => ['name' => 'bianchi.com', 'owner' => 6, 'plan' => 'g-basic'],
    ]);
    $server = syncServer();
    $pro = syncProduct('Hosting Pro');
    syncProduct('Hosting Basic');
    $rossi = Client::factory()->create(['email' => 'INFO@rossi.it', 'company_name' => 'Rossi Srl']);
    $linked = Service::factory()->create(['client_id' => Client::factory()->create()->id, 'product_id' => $pro->id, 'server_id' => $server->id, 'status' => 'active']);
    $linked->forceFill(['module_data' => ['plesk_domain_id' => '12']])->save();

    $rows = collect($this->actingAs(syncAdmin(), 'admin')
        ->get(route('admin.config.servers.sync', $server))
        ->assertOk()
        ->assertSee('Hosting Pro')
        ->viewData('rows'))->keyBy('domain');

    expect($rows['rossi.it'])
        ->service->toBeNull()
        ->client_id->toBe($rossi->id)
        ->product_id->toBe($pro->id)
        ->plan->toBe('Hosting Pro')
        ->next_due_date->toBe('2027-02-20')
        ->and($rows['bianchi.com']['service']->id)->toBe($linked->id);
});

it('fills client, price, cycle and renewal from an uploaded price list', function () {
    fakeSyncPlesk([11 => ['name' => 'rossi.it', 'owner' => 5]]);
    $server = syncServer();
    $client = Client::factory()->create();
    $csv = "domain;client_id;amount;billing_cycle;next_due_date\nRossi.it;{$client->id};60,00;7 Months;2027-03-15\nnobody.it;999999;10;Annually;2027-01-01\n";

    $this->actingAs(syncAdmin(), 'admin')
        ->post(route('admin.config.servers.sync.price-list', $server), [
            'price_list' => UploadedFile::fake()->createWithContent('listino.csv', $csv),
        ])->assertRedirect(route('admin.config.servers.sync', $server));

    $row = $this->get(route('admin.config.servers.sync', $server))->assertOk()->viewData('rows')[0];

    expect($row)
        ->client_id->toBe($client->id)
        ->amount->toBe('60.00')
        ->billing_cycle->toBe('7months')
        ->next_due_date->toBe('2027-03-15')
        ->from_price_list->toBeTrue();
});

it('imports the selected accounts as services linked to their subscription', function () {
    fakeSyncPlesk([
        11 => ['name' => 'rossi.it', 'owner' => 5, 'status' => 16, 'created' => '2019-02-20'],
        12 => ['name' => 'bianchi.com', 'owner' => 6],
    ]);
    $server = syncServer();
    $product = syncProduct('Hosting Pro');
    $client = Client::factory()->create();

    $this->actingAs(syncAdmin(), 'admin')
        ->post(route('admin.config.servers.sync.import', $server), ['rows' => [
            11 => ['client_id' => $client->id, 'product_id' => $product->id, 'billing_cycle' => 'Annually', 'amount' => '60', 'next_due_date' => '2027-02-20'],
        ]])->assertRedirect()->assertSessionHas('success');

    $service = Service::where('server_id', $server->id)->sole();
    expect($service)
        ->client_id->toBe($client->id)
        ->domain->toBe('rossi.it')
        ->status->toBe('suspended')
        ->username->toBe('rossi')
        ->billing_cycle->toBe('annually')
        ->and((float) $service->amount)->toBe(60.0)
        ->and($service->next_due_date->toDateString())->toBe('2027-02-20')
        ->and($service->registration_date->toDateString())->toBe('2019-02-20')
        ->and($service->module_data)->toMatchArray(['plesk_domain_id' => '11', 'plesk_client_id' => '5']);

    Http::assertNotSent(fn (Request $request) => preg_match('/<(set|del|add)>/', $request->body()) === 1);
});

it('offers the cycles the product sells and imports on a term of months', function () {
    fakeSyncPlesk([11 => ['name' => 'rossi.it', 'owner' => 5, 'plan' => 'g-pro']]);
    $server = syncServer();
    $product = syncProduct('Hosting Pro');
    $currency = Currency::getDefault() ?? Currency::create(['code' => 'USD', 'prefix' => '$', 'suffix' => '', 'format' => 1, 'rate' => 1.0, 'is_default' => true]);
    Pricing::create(['type' => 'product', 'currency_id' => $currency->id, 'rel_id' => $product->id, 'monthly' => 10]);
    ProductTerm::create(['product_id' => $product->id, 'currency_id' => $currency->id, 'months' => 7, 'price' => 63.0]);
    $client = Client::factory()->create();

    $row = $this->actingAs(syncAdmin(), 'admin')
        ->get(route('admin.config.servers.sync', $server))
        ->assertOk()
        ->assertSee(cycle_label('7months'))
        ->viewData('rows')[0];

    expect($row)
        ->cycles->toBe(['monthly', '7months'])
        ->billing_cycle->toBe('monthly')
        ->amount->toBe('10.00');

    $this->post(route('admin.config.servers.sync.import', $server), ['rows' => [
        11 => ['client_id' => $client->id, 'product_id' => $product->id, 'billing_cycle' => '7months', 'amount' => '63', 'next_due_date' => '2027-06-01'],
    ]])->assertSessionHas('success');

    expect(Service::where('server_id', $server->id)->sole()->billing_cycle)->toBe('7months');
});

it('does not import an account a live service already manages', function () {
    fakeSyncPlesk([11 => ['name' => 'rossi.it', 'owner' => 5]]);
    $server = syncServer();
    $product = syncProduct('Hosting Pro');
    $existing = Service::factory()->create(['client_id' => Client::factory()->create()->id, 'product_id' => $product->id, 'server_id' => $server->id, 'status' => 'active']);
    $existing->forceFill(['module_data' => ['plesk_domain_id' => '11']])->save();

    $this->actingAs(syncAdmin(), 'admin')
        ->post(route('admin.config.servers.sync.import', $server), ['rows' => [
            11 => ['client_id' => Client::factory()->create()->id, 'product_id' => $product->id, 'billing_cycle' => 'Annually', 'amount' => '60'],
        ]])->assertSessionHas('error');

    expect(Service::where('server_id', $server->id)->count())->toBe(1);
});

it('leaves no service behind when the subscription cannot be linked', function () {
    fakeSyncPlesk([11 => ['name' => 'rossi.it', 'owner' => 5]]);
    $server = syncServer();
    $product = syncProduct('Hosting Pro');
    $module = app(ServerAccountSync::class)->moduleFor($server);

    $result = app(ServerAccountSync::class)->import($server, $module,
        ['id' => 'not-a-number', 'domain' => 'rossi.it', 'status' => 'active', 'created' => null],
        ['client_id' => Client::factory()->create()->id, 'product_id' => $product->id, 'billing_cycle' => 'Annually', 'amount' => '60', 'next_due_date' => null],
        'admin');

    expect($result['success'])->toBeFalse()
        ->and(Service::where('server_id', $server->id)->exists())->toBeFalse();
});

it('lets staff without the service permissions look but not import', function () {
    fakeSyncPlesk([11 => ['name' => 'rossi.it', 'owner' => 5]]);
    $server = syncServer();
    $admin = syncAdmin(['manage_servers']);

    $this->actingAs($admin, 'admin')->get(route('admin.config.servers.sync', $server))
        ->assertOk()
        ->assertSee(__('admin.server_sync.no_permission'));

    $this->post(route('admin.config.servers.sync.import', $server), ['rows' => [11 => ['client_id' => 1]]])
        ->assertForbidden();
});

it('offers the sync page on servers whose accounts it can list', function () {
    $plesk = syncServer();
    $proxmox = Server::factory()->create(['type' => 'proxmox']);

    $this->actingAs(syncAdmin(), 'admin')
        ->get(route('admin.config.servers'))
        ->assertOk()
        ->assertSee(route('admin.config.servers.sync', $plesk))
        ->assertDontSee(route('admin.config.servers.sync', $proxmox));
});
