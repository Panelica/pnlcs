<?php

use App\Models\Client;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Models\User;
use Modules\Servers\Proxmox\ProxmoxModule;
use Tests\Support\FakeProxmox;

require_once __DIR__.'/../Support/proxmox_helpers.php';

/*
 * SSH keys for a virtual server: given at the order or on a reinstall, and put
 * on the machine when it is built.
 *
 * The Proxmox module already read pve_sshkeys into cloud-init, but nothing
 * wrote it, and a container never got the keys at all.
 */

function sshKey(string $comment = 'me@laptop'): string
{
    return 'ssh-ed25519 '.base64_encode(pack('N', 11).'ssh-ed25519'.pack('N', 32).random_bytes(32)).' '.$comment;
}

function sshVpsProduct(): Product
{
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id, 'type' => 'vps', 'server_type' => 'proxmox',
        'hidden' => false, 'retired' => false,
        'config_options' => json_encode(['pve_type' => 'qemu', 'pve_template' => '9000', 'pve_storage' => 'local-lvm', 'pve_bridge' => 'vmbr0', 'pve_cores' => 1, 'pve_memory' => 1024, 'pve_disk' => 10]),
    ]);
    App\Models\Pricing::create(['type' => 'product', 'currency_id' => App\Models\Currency::getDefault()?->id ?? App\Models\Currency::factory()->create()->id, 'rel_id' => $product->id, 'monthly' => 10]);

    return $product;
}

it('accepts OpenSSH public keys and refuses anything else', function () {
    $a = sshKey('a');
    $b = sshKey('b');

    expect(ProxmoxModule::normaliseSshKeys("  {$a}\r\n\r\n{$b}\n"))->toBe("{$a}\n{$b}")
        ->and(ProxmoxModule::normaliseSshKeys(''))->toBe('')
        ->and(ProxmoxModule::normaliseSshKeys("-----BEGIN OPENSSH PRIVATE KEY-----\nb3BlbnNzaC1rZXktdjEAAAAA\n-----END OPENSSH PRIVATE KEY-----"))->toBeNull()
        ->and(ProxmoxModule::normaliseSshKeys('ssh-ed25519 not-base64!'))->toBeNull()
        // The data does not belong to the type it claims (a truncated or edited paste).
        ->and(ProxmoxModule::normaliseSshKeys('ssh-rsa '.explode(' ', $a)[1]))->toBeNull()
        ->and(ProxmoxModule::normaliseSshKeys(implode("\n", array_map(fn ($i) => sshKey("k{$i}"), range(1, 11)))))->toBeNull();
});

it('carries the keys from the order to the service', function () {
    $product = sshVpsProduct();
    $user = User::factory()->create();
    $user->clients()->attach(Client::factory()->create()->id);
    $key = sshKey();

    test()->actingAs($user)->get(route('client.store.configure', $product))->assertOk()->assertSee('name="ssh_keys"', false);

    test()->actingAs($user)->post(route('client.cart.add'), [
        'product_id' => $product->id, 'billing_cycle' => 'monthly', 'domain' => 'box1', 'domain_option' => 'own', 'ssh_keys' => $key."\n",
    ])->assertRedirect(route('client.cart.index'));
    test()->actingAs($user)->post(route('client.cart.checkout'), ['payment_method' => 'banktransfer', 'terms' => '1'])->assertRedirect();

    $service = Service::latest('id')->firstOrFail();
    expect(((array) $service->module_data)['pve_sshkeys'] ?? null)->toBe($key);
});

it('refuses a pasted private key at the order', function () {
    $product = sshVpsProduct();
    $user = User::factory()->create();
    $user->clients()->attach(Client::factory()->create()->id);

    test()->actingAs($user)->post(route('client.cart.add'), [
        'product_id' => $product->id, 'billing_cycle' => 'monthly', 'ssh_keys' => '-----BEGIN OPENSSH PRIVATE KEY-----',
    ])->assertSessionHasErrors('ssh_keys');
});

it('gives a new container the keys', function () {
    $pve = FakeProxmox::install();
    $key = sshKey();
    $service = pveService(pveServer(), ['pve_type' => 'lxc', 'pve_ostemplate' => 'local:vztmpl/debian-12-standard_12.7-1_amd64.tar.zst'], ['pve_sshkeys' => $key]);

    $result = (new ProxmoxModule)->create($service);

    expect($result['success'])->toBeTrue($result['message'])
        ->and($pve->sent('POST', 'nodes/pve/lxc')[0]['params']['ssh-public-keys'] ?? null)->toBe($key);
});

it('lets the customer change the keys on a reinstall, and refuses a bad one', function () {
    $pve = FakeProxmox::install()->template(9000);
    $service = pveService(pveServer(), [], ['pve_sshkeys' => sshKey('old')]);
    pveOwnedGuest($pve, $service, 790);
    $user = pveCustomer($service);
    $new = sshKey('new');

    test()->actingAs($user)->get(route('client.services.show', $service))->assertOk()->assertSee('name="ssh_keys"', false);

    test()->actingAs($user)->postJson(route('client.services.vps.reinstall', $service), ['image' => '9000', 'confirm' => 'vps1.example.com', 'ssh_keys' => 'not a key'])
        ->assertUnprocessable()->assertJsonValidationErrors('ssh_keys');
    expect($pve->sent('DELETE', '.*'))->toBe([]);

    test()->actingAs($user)->postJson(route('client.services.vps.reinstall', $service), ['image' => '9000', 'confirm' => 'vps1.example.com', 'ssh_keys' => $new])
        ->assertOk()->assertJsonPath('success', true);
    expect($service->fresh()->module_data['pve_sshkeys'])->toBe($new);
});
