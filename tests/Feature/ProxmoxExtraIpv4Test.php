<?php

use App\Models\ConfigOption;
use App\Models\ConfigOptionGroup;
use App\Models\Service;
use App\Models\ServiceConfigOption;
use Illuminate\Validation\ValidationException;
use Modules\Servers\Proxmox\Ipv4Pool;
use Modules\Servers\Proxmox\ProxmoxModule;
use Modules\Servers\Proxmox\ProxmoxOrderOptions;
use Modules\Servers\Proxmox\ProxmoxPlan;
use Tests\Support\FakeProxmox;

require_once __DIR__.'/../Support/proxmox_helpers.php';

/*
 * #321. Additional IPv4 addresses sold with a Proxmox server: the extra_ipv4
 * option takes them from the server's pool, puts each on a network card of
 * its own and gives them back when the service ends. Bought later, they come
 * through the option upgrade, which calls changePackage().
 */

/** A configurable quantity option "extra_ipv4" on the service, set to $qty. */
function xipQuantity(Service $service, int $qty): void
{
    $option = ConfigOption::where('option_name', 'extra_ipv4|Additional IPv4')->first()
        ?? ConfigOption::create(['group_id' => ConfigOptionGroup::create(['name' => 'Extra IPs'])->id, 'option_name' => 'extra_ipv4|Additional IPv4', 'option_type' => 'quantity']);
    ServiceConfigOption::updateOrCreate(['service_id' => $service->id, 'config_id' => $option->id], ['qty' => $qty]);
}

it('gives a new virtual machine its additional addresses, one network card each, without a gateway', function () {
    $pve = FakeProxmox::install()->template(9000);
    $server = pveServer(['ipv4_pool' => '203.0.113.1-203.0.113.10/24 gw 203.0.113.1']);
    $service = pveService($server, ['pve_ipv4' => 'pool', 'pve_extra_ipv4' => 2]);

    $result = (new ProxmoxModule)->create($service);

    expect($result['success'])->toBeTrue($result['message']);
    $data = $service->fresh()->module_data;
    $config = $pve->guests[$data['proxmox_vmid']]['config'];
    expect($data['pve_ipv4'])->toBe('203.0.113.2')
        ->and(array_column($data['pve_ipv4_extra'], 'address'))->toBe(['203.0.113.3', '203.0.113.4'])
        ->and($config['ipconfig0'])->toBe('ip=203.0.113.2/24,gw=203.0.113.1')
        ->and($config['ipconfig1'])->toBe('ip=203.0.113.3/24')
        ->and($config['ipconfig2'])->toBe('ip=203.0.113.4/24')
        ->and($config['net1'])->toBe('virtio,bridge=vmbr0')
        ->and($config['net2'])->toBe('virtio,bridge=vmbr0');
});

it('gives a container eth1 upward, with the gateway only on eth0', function () {
    $pve = FakeProxmox::install();
    $server = pveServer(['ipv4_pool' => '198.51.100.10-198.51.100.20/28 gw 198.51.100.1']);
    $service = pveService($server, [
        'pve_type' => 'lxc', 'pve_ostemplate' => 'local:vztmpl/debian-12-standard_12.7-1_amd64.tar.zst',
        'pve_ipv4' => 'pool', 'pve_extra_ipv4' => 1,
    ]);

    $result = (new ProxmoxModule)->create($service);

    expect($result['success'])->toBeTrue($result['message']);
    $config = $pve->guests[$result['data']['vmid']]['config'];
    expect($config['net0'])->toContain('ip=198.51.100.10/28,gw=198.51.100.1')
        ->and($config['net1'])->toBe('name=eth1,bridge=vmbr0,ip=198.51.100.11/28');
});

it('makes nothing when the pool cannot give every address', function () {
    $pve = FakeProxmox::install()->template(9000);
    // Two usable addresses: the server's own and one more, but three are sold.
    $server = pveServer(['ipv4_pool' => '203.0.113.1-203.0.113.3/24 gw 203.0.113.1']);
    $service = pveService($server, ['pve_ipv4' => 'pool', 'pve_extra_ipv4' => 2]);

    $result = (new ProxmoxModule)->create($service);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('2 additional address')
        ->and($pve->sent('POST', 'clone'))->toBe([])
        ->and($service->fresh()->module_data['pve_ipv4_extra'] ?? null)->toBeNull();
});

it('adds bought addresses to a running server, keeps the ones it has, and takes away only its own cards', function () {
    $pve = FakeProxmox::install();
    $server = pveServer(['ipv4_pool' => '203.0.113.1-203.0.113.10/24 gw 203.0.113.1']);
    $service = pveService($server, ['pve_ipv4' => 'pool']);
    pveOwnedGuest($pve, $service, 740, 'qemu', 'running', ['net3' => 'virtio=BC:24:11:AA:BB:CC,bridge=vmbr1']);
    $service->forceFill(['module_data' => $service->module_data + ['pve_ipv4' => '203.0.113.2', 'pve_ipv4_prefix' => 24, 'pve_ipv4_gateway' => '203.0.113.1']])->save();
    $module = new ProxmoxModule;

    xipQuantity($service, 1);
    $one = $module->changePackage($service->fresh(['server', 'product']), ['config_options' => $service->product->config_options]);
    $first = $service->fresh()->module_data['pve_ipv4_extra'][0]['address'];
    $pve->guests[740]['config']['net1'] = 'virtio=BC:24:11:00:00:01,bridge=vmbr0';

    xipQuantity($service, 2);
    $two = $module->changePackage($service->fresh(['server', 'product']), ['config_options' => $service->product->config_options]);
    $config = $pve->guests[740]['config'];

    expect($one['success'])->toBeTrue($one['message'])
        ->and($two['message'])->toContain('restart it from the panel')
        ->and($first)->toBe('203.0.113.3')
        ->and(array_column($service->fresh()->module_data['pve_ipv4_extra'], 'address'))->toBe(['203.0.113.3', '203.0.113.4'])
        // The card it already had keeps its MAC.
        ->and($config['net1'])->toBe('virtio=BC:24:11:00:00:01,bridge=vmbr0')
        ->and($config['ipconfig2'])->toBe('ip=203.0.113.4/24');

    xipQuantity($service, 0);
    $module->changePackage($service->fresh(['server', 'product']), ['config_options' => $service->product->config_options]);
    $config = $pve->guests[740]['config'];

    expect($config)->not->toHaveKeys(['net1', 'net2', 'ipconfig1', 'ipconfig2'])
        ->and($config['net3'])->toBe('virtio=BC:24:11:AA:BB:CC,bridge=vmbr1')
        ->and(Ipv4Pool::taken($server))->toBe(['203.0.113.2']);
});

it('does not hand out an additional address twice, and frees them when the service ends', function () {
    $pve = FakeProxmox::install()->template(9000);
    $server = pveServer(['ipv4_pool' => '203.0.113.1-203.0.113.4/24 gw 203.0.113.1']);
    $a = pveService($server, ['pve_ipv4' => 'pool', 'pve_extra_ipv4' => 1]);
    $b = pveService($server, ['pve_ipv4' => 'pool']);
    $c = pveService($server, ['pve_ipv4' => 'pool']);

    (new ProxmoxModule)->create($a);
    (new ProxmoxModule)->create($b);
    expect($b->fresh()->module_data['pve_ipv4'])->toBe('203.0.113.4')
        ->and((new ProxmoxModule)->create($c)['success'])->toBeFalse();

    expect((new ProxmoxModule)->terminate($a->fresh(['server', 'product']))['success'])->toBeTrue();
    expect($a->fresh()->module_data['pve_ipv4_extra'])->toBeNull()
        ->and(Ipv4Pool::freeCount($server))->toBe(2);
});

it('ignores additional addresses on a product that uses DHCP', function () {
    FakeProxmox::install()->template(9000);
    $service = pveService(pveServer(['ipv4_pool' => '203.0.113.1-203.0.113.9/24 gw 203.0.113.1']), ['pve_extra_ipv4' => 2]);

    expect((new ProxmoxModule)->create($service)['success'])->toBeTrue()
        ->and($service->fresh()->module_data['pve_ipv4_extra'] ?? null)->toBeNull();
});

it('reads zero additional addresses as a choice, and caps the number', function () {
    $service = pveService(pveServer(), ['pve_ipv4' => 'pool', 'pve_extra_ipv4' => 2]);

    xipQuantity($service, 0);
    expect(ProxmoxPlan::forService($service->fresh())->extra_ipv4)->toBe(0);

    xipQuantity($service, 99);
    expect(ProxmoxPlan::forService($service->fresh())->extra_ipv4)->toBe(ProxmoxPlan::MAX_EXTRA_IPV4);
});

it('builds the option on the product page, "no additional address" included', function () {
    $service = pveService(pveServer(), ['pve_ipv4' => 'pool']);

    ProxmoxOrderOptions::create($service->product, ['extra_ipv4' => ['choices' => [
        ['value' => '0', 'price' => 0], ['value' => '1', 'price' => 3], ['value' => '2', 'price' => 6],
    ]]]);
    $linked = ProxmoxOrderOptions::linked($service->product);

    expect($linked[0]['key'])->toBe('extra_ipv4')
        ->and(array_column($linked[0]['choices'], 'label'))->toBe(['No additional address', '1 additional IPv4 address', '2 additional IPv4 addresses']);

    expect(fn () => ProxmoxOrderOptions::create($service->product->fresh(), ['extra_ipv4' => ['choices' => [['value' => '40']]]]))
        ->toThrow(ValidationException::class);
});

it('shows staff which address belongs to which service, and finds a service by an additional one', function () {
    FakeProxmox::install();
    $server = pveServer(['ipv4_pool' => '203.0.113.1-203.0.113.9/24 gw 203.0.113.1']);
    $service = pveService($server, ['pve_ipv4' => 'pool'], [
        'pve_ipv4' => '203.0.113.2', 'pve_ipv4_extra' => [['address' => '203.0.113.7', 'prefix' => 24, 'gateway' => '203.0.113.1']],
    ], ['status' => 'active']);
    $admin = pveAdmin();

    $this->actingAs($admin, 'admin')->get(route('admin.config.servers.capacity', $server))->assertOk()
        ->assertSeeInOrder(['IPv4 pool', '2 in use, 6 free.', '203.0.113.2', 'Server address', '203.0.113.7', 'Additional', '#'.$service->id]);

    $this->actingAs($admin, 'admin')->get(route('admin.services.index', ['q' => '203.0.113.7']))->assertOk()
        ->assertSee($service->domain);
});

it('does not sell additional addresses on a product that takes its address by DHCP', function () {
    $service = pveService(pveServer());   // IPv4 by DHCP: the module gives no additional address

    expect(fn () => ProxmoxOrderOptions::create($service->product, ['extra_ipv4' => ['choices' => [
        ['value' => '0', 'price' => 0], ['value' => '1', 'price' => 3],
    ]]]))->toThrow(ValidationException::class, __('proxmox.product.opt_extra_ipv4_needs_pool'));
    expect(ProxmoxOrderOptions::linked($service->product->fresh()))->toBe([]);

    // "No additional address" alone sells nothing, so it may stay.
    ProxmoxOrderOptions::create($service->product->fresh(), ['extra_ipv4' => ['choices' => [['value' => '0', 'price' => 0]]]]);
    expect(ProxmoxOrderOptions::linked($service->product->fresh())[0]['key'])->toBe('extra_ipv4');
});

it('lists the additional addresses with their card on the admin VPS panel', function () {
    $pve = FakeProxmox::install();
    $server = pveServer(['ipv4_pool' => '203.0.113.1-203.0.113.9/24 gw 203.0.113.1']);
    $service = pveService($server, ['pve_ipv4' => 'pool'], [], ['status' => 'active']);
    pveOwnedGuest($pve, $service, 801);
    $service->forceFill(['module_data' => $service->module_data + [
        'pve_ipv4' => '203.0.113.2',
        'pve_ipv4_extra' => [['address' => '203.0.113.7', 'prefix' => 24, 'gateway' => '203.0.113.1'], ['address' => '203.0.113.8', 'prefix' => 24, 'gateway' => '203.0.113.1']],
    ]])->save();

    $this->actingAs(pveAdmin(), 'admin')->get(route('admin.services.show', $service))->assertOk()
        ->assertSee(__('proxmox.admin.extra_addresses'))
        ->assertSee('203.0.113.7/24 (net1), 203.0.113.8/24 (net2)');
});
