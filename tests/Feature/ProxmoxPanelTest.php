<?php

use App\Models\ConfigOption;
use App\Models\ConfigOptionGroup;
use App\Models\ConfigOptionSub;
use App\Models\Pricing;
use App\Models\ServiceConfigOption;
use Modules\Servers\Proxmox\ProxmoxModule;
use Modules\Servers\Proxmox\ProxmoxOrderOptions;
use Modules\Servers\Proxmox\ProxmoxPlan;
use Tests\Support\FakeProxmox;

/*
 * The VPS panel the customer and the admin share, the order options a
 * Proxmox product builds on its own page, and the admin's "add a service by
 * hand" path for virtual servers.
 */

require_once __DIR__.'/../Support/proxmox_helpers.php';

beforeEach(function () {
    \App\Models\Currency::getDefault() ?? \App\Models\Currency::factory()->default()->create();
});

// ---------------------------------------------------------------------------
// One panel, two sides
// ---------------------------------------------------------------------------

it('gives the admin the same VPS panel the customer has, on the admin routes', function () {
    $pve = FakeProxmox::install()->template(9000);
    $service = pveService(pveServer(), ['pve_snapshots' => 2]);
    pveOwnedGuest($pve, $service, 790);
    $admin = pveAdmin();

    test()->actingAs($admin, 'admin')->get(route('admin.services.show', $service))
        ->assertOk()->assertSee('id="vps"', false)->assertSee('data-power="reset"', false)
        ->assertSee(json_encode(route('admin.services.vps.power', $service)), false)
        ->assertSee('id="vps-snapshots"', false);

    test()->actingAs(pveCustomer($service))->get(route('client.services.show', $service))
        ->assertOk()->assertSee('id="vps"', false)->assertSee('data-power="reset"', false)
        ->assertSee(json_encode(route('client.services.vps.power', $service)), false);

    test()->actingAs($admin, 'admin')->getJson(route('admin.services.vps.status', $service))->assertOk()->assertJsonPath('vmid', 790);
    test()->actingAs($admin, 'admin')->getJson(route('admin.services.vps.graphs', $service).'?timeframe=day')
        ->assertOk()->assertJsonPath('available', true)->assertJsonPath('timeframe', 'day');
    test()->actingAs($admin, 'admin')->postJson(route('admin.services.vps.snapshots.action', $service), ['action' => 'create', 'name' => 'before-upgrade'])
        ->assertOk()->assertJsonPath('success', true);
});

it('lets the customer and the admin hard-reset the server', function () {
    $pve = FakeProxmox::install();
    $service = pveService(pveServer());
    pveOwnedGuest($pve, $service, 791);

    test()->actingAs(pveCustomer($service))->postJson(route('client.services.vps.power', $service), ['action' => 'reset'])
        ->assertOk()->assertJsonPath('success', true);
    test()->actingAs(pveAdmin(), 'admin')->postJson(route('admin.services.vps.power', $service), ['action' => 'reset'])
        ->assertOk()->assertJsonPath('success', true);

    expect($pve->sent('POST', 'nodes/pve/qemu/791/status/reset'))->toHaveCount(2);
});

it('lets staff work on a suspended service\'s server but not on a terminated one', function () {
    $pve = FakeProxmox::install();
    $service = pveService(pveServer());
    pveOwnedGuest($pve, $service, 792, 'qemu', 'stopped');
    $admin = pveAdmin();

    $service->forceFill(['status' => 'suspended'])->save();
    test()->actingAs($admin, 'admin')->postJson(route('admin.services.vps.power', $service), ['action' => 'start'])->assertOk();
    expect($pve->guests[792]['status'])->toBe('running');

    $service->forceFill(['status' => 'terminated'])->save();
    test()->actingAs($admin, 'admin')->postJson(route('admin.services.vps.power', $service), ['action' => 'stop'])->assertStatus(409);
    expect($pve->guests[792]['status'])->toBe('running');
    // Looking is still allowed.
    test()->actingAs($admin, 'admin')->getJson(route('admin.services.vps.status', $service))->assertOk();
});

it('keeps the VPS routes behind the admin\'s service permission', function () {
    $pve = FakeProxmox::install();
    $service = pveService(pveServer());
    pveOwnedGuest($pve, $service, 793);
    $viewer = \App\Models\Admin::factory()->create(['role_id' => \App\Models\AdminRole::factory()->create(['permissions' => ['view_services']])->id]);

    test()->actingAs($viewer, 'admin')->postJson(route('admin.services.vps.power', $service), ['action' => 'stop'])->assertForbidden();
    expect($pve->guests[793]['status'])->toBe('running');
});

it('reports how full the root filesystem is when the guest agent runs, and does not make one up when it does not', function () {
    $pve = FakeProxmox::install();
    $service = pveService(pveServer());
    pveOwnedGuest($pve, $service, 794, 'qemu', 'running', ['agent' => '1']);
    $module = new ProxmoxModule;

    $pve->agentRunning = true;
    expect($module->vmStatus($service->fresh())['disk'])->toBe(['used' => 3.5, 'max' => 20.0, 'fs_size' => 20.0]);

    $pve->agentRunning = false;
    expect($module->vmStatus($service->fresh())['disk'])->toBe(['used' => null, 'max' => 20.0, 'fs_size' => null]);
});

it('does not show the customer the hypervisor\'s address', function () {
    $pve = FakeProxmox::install();
    $service = pveService(pveServer([], ['hostname' => 'pve-host.example.com']));
    pveOwnedGuest($pve, $service, 795);

    test()->actingAs(pveCustomer($service))->get(route('client.services.show', $service))
        ->assertOk()->assertDontSee('pve-host.example.com');
});

// ---------------------------------------------------------------------------
// Order options built on the product page
// ---------------------------------------------------------------------------

it('builds the order options of a product in one step, prices longer periods from the month, and reads them back', function () {
    FakeProxmox::install();
    $service = pveService(pveServer(), ['pve_os_choices' => [['id' => '9001', 'name' => 'Ubuntu 24.04']]]);
    $product = $service->product;

    $group = ProxmoxOrderOptions::create($product, [
        'os' => ['title' => 'Operating system', 'choices' => [['value' => '9000', 'label' => 'Debian 12'], ['value' => '9001', 'label' => '', 'price' => 0]]],
        'memory' => ['title' => '', 'choices' => [['value' => '2048', 'price' => 0], ['value' => '4096', 'price' => 5], ['value' => '1536', 'price' => 2]]],
        'cores' => ['choices' => [['value' => '1'], ['value' => '4', 'price' => 8]]],
    ]);

    $options = ConfigOption::where('group_id', $group->id)->orderBy('sort_order')->get();
    expect($options->pluck('option_name')->all())->toBe(['os|Operating system', 'memory|Memory', 'cores|CPU cores'])
        ->and($product->configOptionGroups()->pluck('config_option_groups.id')->all())->toBe([$group->id]);

    $memory = ConfigOptionSub::where('config_id', $options[1]->id)->orderBy('sort_order')->get();
    expect($memory->pluck('option_name')->all())->toBe(['2048|2 GB', '4096|4 GB', '1536|1.5 GB'])
        ->and(ConfigOptionSub::where('config_id', $options[2]->id)->pluck('option_name')->all())->toBe(['1|1 core', '4|4 cores'])
        ->and(ConfigOptionSub::where('config_id', $options[0]->id)->pluck('option_name')->all())->toBe(['9000|Debian 12', '9001|Ubuntu 24.04']);

    $price = Pricing::where('type', ConfigOptionSub::PRICING_TYPE)->where('rel_id', $memory[1]->id)->first();
    expect((float) $price->monthly)->toBe(5.0)->and((float) $price->quarterly)->toBe(15.0)->and((float) $price->annually)->toBe(60.0);

    $linked = collect(ProxmoxOrderOptions::linked($product->fresh()))->keyBy('key');
    expect($linked->keys()->all())->toBe(['os', 'memory', 'cores'])
        ->and($linked['memory']['choices'][1])->toBe(['value' => '4096', 'label' => '4 GB', 'monthly' => 5.0]);

    // What the customer picks is what the server is built with.
    ServiceConfigOption::create(['service_id' => $service->id, 'config_id' => $options[1]->id, 'option_id' => $memory[1]->id, 'qty' => 1]);
    ServiceConfigOption::create(['service_id' => $service->id, 'config_id' => $options[0]->id,
        'option_id' => ConfigOptionSub::where('config_id', $options[0]->id)->where('option_name', 'like', '9001|%')->value('id'), 'qty' => 1]);
    $plan = ProxmoxPlan::forService($service->fresh());
    expect($plan->memory)->toBe(4096)->and($plan->template)->toBe('9001');
});

it('refuses an operating system the product does not offer, a second option of the same kind, and nothing at all', function () {
    FakeProxmox::install();
    $product = pveService(pveServer())->product;

    expect(fn () => ProxmoxOrderOptions::create($product, ['os' => ['choices' => [['value' => '100']]]]))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
    expect(fn () => ProxmoxOrderOptions::create($product, ['memory' => ['choices' => [['value' => '12']]]]))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
    expect(fn () => ProxmoxOrderOptions::create($product, []))->toThrow(\Illuminate\Validation\ValidationException::class);
    expect(ConfigOptionGroup::count())->toBe(0);

    ProxmoxOrderOptions::create($product, ['disk' => ['choices' => [['value' => '40']]]]);
    expect(fn () => ProxmoxOrderOptions::create($product, ['disk' => ['choices' => [['value' => '80']]]]))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
    expect(ConfigOptionGroup::count())->toBe(1);
});

it('builds the order options from the product page, and only for a Proxmox product', function () {
    FakeProxmox::install();
    $product = pveService(pveServer())->product;
    $admin = pveAdmin();

    test()->actingAs($admin, 'admin')->postJson(route('admin.products.proxmox-options', $product), [
        'options' => ['memory' => ['title' => 'RAM', 'choices' => [['value' => '2048', 'price' => '0'], ['value' => '8192', 'price' => '20']]]],
    ])->assertOk()->assertJsonPath('success', true)->assertJsonPath('linked.0.title', 'RAM')->assertJsonPath('linked.0.choices.1.label', '8 GB');

    test()->actingAs($admin, 'admin')->get(route('admin.products.edit', $product))
        ->assertOk()->assertSee('id="pve-options"', false)->assertSee('8 GB');

    $product->forceFill(['server_type' => 'panelica'])->save();
    test()->actingAs($admin, 'admin')->postJson(route('admin.products.proxmox-options', $product), [
        'options' => ['cores' => ['choices' => [['value' => '2']]]],
    ])->assertUnprocessable();
});

it('shows customers the option label, not the key the module reads', function () {
    $group = ConfigOptionGroup::create(['name' => 'VPS']);
    $option = ConfigOption::create(['group_id' => $group->id, 'option_name' => 'memory|RAM', 'option_type' => 'dropdown']);
    $sub = ConfigOptionSub::create(['config_id' => $option->id, 'option_name' => '4096|4 GB']);
    $plain = ConfigOption::create(['group_id' => $group->id, 'option_name' => 'Extra IP', 'option_type' => 'quantity']);

    expect($option->displayName())->toBe('RAM')->and($sub->displayName())->toBe('4 GB')->and($plain->displayName())->toBe('Extra IP');

    $row = new ServiceConfigOption(['qty' => 1]);
    $row->setRelation('option', $option)->setRelation('sub', $sub);
    expect($row->label())->toBe('RAM: 4 GB');
});

it('asks for a hostname, not a domain purchase, when a virtual server is ordered', function () {
    FakeProxmox::install();
    $product = pveService(pveServer())->product;
    $product->forceFill(['slug' => 'vps-small', 'show_domain_options' => true])->save();
    Pricing::updateOrCreate(['type' => 'product', 'rel_id' => $product->id, 'currency_id' => \App\Models\Currency::getDefault()?->id ?? 1],
        ['monthly' => 5, 'quarterly' => -1, 'semiannually' => -1, 'annually' => -1, 'biennially' => -1, 'triennially' => -1]);

    test()->get(route('client.store.configure', $product))
        ->assertOk()->assertSee('id="vpsHostname"', false)->assertDontSee('name="domain_option" value="register"', false);
});

// ---------------------------------------------------------------------------
// Adding a virtual server to a customer by hand
// ---------------------------------------------------------------------------

it('lists the guests an operator can link, without templates, naming the ones already taken', function () {
    $pve = FakeProxmox::install()->template(9000)->foreignGuest(801);
    $service = pveService(pveServer());
    pveOwnedGuest($pve, $service, 802);

    $list = (new ProxmoxModule)->listAccounts($service->server);
    $accounts = collect($list)->keyBy('id');

    expect(array_column($list, 'id'))->toBe(['801', '802'])
        ->and($accounts[801]['username'])->toBe('#801 operator-801')
        ->and($accounts[802]['status'])->toContain((string) $service->id);
});

it('adds a virtual server by hand with the customer\'s options, or links one that already runs', function () {
    $pve = FakeProxmox::install()->template(9000)->foreignGuest(810, 'qemu', 'running');
    $server = pveServer();
    $product = pveService($server)->product;
    $group = ProxmoxOrderOptions::create($product, ['memory' => ['choices' => [['value' => '2048'], ['value' => '4096', 'price' => 5]]]]);
    $four = ConfigOptionSub::whereHas('option', fn ($q) => $q->where('group_id', $group->id))->where('option_name', 'like', '4096|%')->first();
    $client = \App\Models\Client::factory()->create();
    $admin = pveAdmin();

    test()->actingAs($admin, 'admin')->get(route('admin.clients.show', ['client' => $client, 'tab' => 'services']))
        ->assertOk()->assertSee('svc-options', false);

    test()->actingAs($admin, 'admin')->post(route('admin.clients.services.store', $client), [
        'product_id' => $product->id, 'server_id' => $server->id, 'domain' => 'vps9.example.com', 'billing_cycle' => 'Monthly',
        'amount' => '10.00', 'status' => 'active', 'provision' => '0', 'config_options' => [$four->option->id => $four->id],
    ])->assertSessionHasNoErrors();
    $added = $client->services()->latest('id')->first();
    expect(ProxmoxPlan::forService($added)->memory)->toBe(4096);

    test()->actingAs($admin, 'admin')->post(route('admin.clients.services.store', $client), [
        'product_id' => $product->id, 'server_id' => $server->id, 'domain' => 'old.example.com', 'billing_cycle' => 'Monthly',
        'amount' => '10.00', 'status' => 'active', 'provision' => '0', 'link_user_id' => '810',
    ])->assertSessionHas('success');
    $linked = $client->services()->latest('id')->first();
    expect((int) $linked->module_data['proxmox_vmid'])->toBe(810)
        ->and($pve->guests[810]['config']['tags'])->toContain('pnlcs-s'.$linked->id)
        ->and($pve->guests[810]['status'])->toBe('running');
});
