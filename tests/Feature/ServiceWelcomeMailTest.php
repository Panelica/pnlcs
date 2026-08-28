<?php

use App\Mail\ServiceWelcomeMail;
use App\Models\Client;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\Service;

/**
 * The activation email now carries the customer's access details: control
 * panel URL + username + password always, an SSH/SFTP block only when the
 * product actually grants shell/SFTP (res_ssh_level jailed|full), and
 * nameservers when the server has them. It must degrade cleanly for a manual
 * service with no server, and never promise SSH the account does not have.
 */
function welcomeService(array $productConfig = [], bool $withServer = true): Service
{
    $client = Client::factory()->create(['first_name' => 'Ada']);
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'config_options' => $productConfig,
    ]);
    $server = $withServer ? Server::factory()->create([
        'hostname' => 'srv1.example.com', 'port' => 8443,
        'nameserver1' => 'ns1.example.com', 'nameserver2' => 'ns2.example.com',
    ]) : null;

    return Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'server_id' => $server?->id,
        'username' => 'adauser',
        'password' => 'S3cretPass!',
        'status' => 'active',
        'domain' => 'ada.example.com',
    ]);
}

function renderWelcome(Service $service): string
{
    return (new ServiceWelcomeMail($service))->render();
}

it('shows the control panel URL, username and password', function () {
    $html = renderWelcome(welcomeService(['res_ssh_level' => 'none']));

    expect($html)->toContain('https://srv1.example.com:8443')
        ->toContain('adauser')
        ->toContain('S3cretPass!');
});

it('shows an SFTP-only note for a jailed product', function () {
    $html = renderWelcome(welcomeService(['res_ssh_level' => 'jailed']));

    expect($html)->toContain(__('email.service_welcome.ssh_heading'))
        ->toContain(__('email.service_welcome.ssh_type_sftp'))
        ->not->toContain(__('email.service_welcome.ssh_type_full'));
});

it('shows a shell note for a full-ssh product', function () {
    $html = renderWelcome(welcomeService(['res_ssh_level' => 'full']));

    expect($html)->toContain(__('email.service_welcome.ssh_type_full'));
});

it('does not promise SSH when the product grants none', function () {
    $html = renderWelcome(welcomeService(['res_ssh_level' => 'none']));

    expect($html)->not->toContain(__('email.service_welcome.ssh_heading'));
});

it('lists nameservers when the server has them', function () {
    $html = renderWelcome(welcomeService(['res_ssh_level' => 'none']));

    expect($html)->toContain('ns1.example.com')->toContain('ns2.example.com');
});

it('renders cleanly for a manual service with no server', function () {
    $html = renderWelcome(welcomeService([], withServer: false));

    // No access section (no panel URL), but the email still renders.
    expect($html)->not->toContain(__('email.service_welcome.access_heading'))
        ->toContain(__('email.service_welcome.provisioned'));
});
