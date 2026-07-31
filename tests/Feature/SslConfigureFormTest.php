<?php

use App\Models\Client;
use App\Models\SslOrder;
use App\Models\User;

/**
 * Configuring a certificate the customer has bought.
 *
 * The page is an Alpine component - x-data="sslConfigForm()" - and the list of
 * approver addresses was filled in by that script alone. The script sat in a
 *
 * @push('scripts') block and the client layout has no @stack, so it was thrown
 * away; and the layout pulls in only the stylesheet through Vite, so Alpine was
 * never loaded either.
 *
 * The approver select therefore rendered with nothing in it, and the controller
 * refuses the form without a choice when the validation method is EMAIL. A
 * certificate could not be configured through the panel at all.
 */
function sslCustomer(): array
{
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);

    $order = SslOrder::create([
        'client_id' => $client->id,
        'domain' => 'example.test',
        'module' => 'gogetssl',
        'status' => 'Awaiting Configuration',
        'order_date' => now(),
    ]);

    return [$user, $order];
}

test('the approver addresses are in the markup, not left to a script', function () {
    [$user, $order] = sslCustomer();

    $response = $this->actingAs($user)
        ->get(route('client.ssl.configure', $order))
        ->assertOk();

    // The conventional addresses a certificate authority will accept, so the
    // select is usable even when the module tells us nothing.
    $response->assertSee('admin@example.test', false)
        ->assertSee('webmaster@example.test', false);
});

test('a script pushed by a client page is not thrown away', function () {
    [$user, $order] = sslCustomer();

    $html = $this->actingAs($user)->get(route('client.ssl.configure', $order))->getContent();

    expect($html)->toContain('function sslConfigForm');
});

test('the form can be submitted with an address from the page', function () {
    [$user, $order] = sslCustomer();

    $this->actingAs($user)->post(route('client.ssl.submitConfiguration', $order), [
        'csr' => str_repeat('A', 120),
        'webserver_type' => 'nginx',
        'validation_method' => 'EMAIL',
        'approver_email' => 'admin@example.test',
        'admin_first_name' => 'Account',
        'admin_last_name' => 'Owner',
        'admin_email' => 'owner@example.test',
        'admin_phone' => '5550000',
        'admin_org' => 'Example',
        'admin_address' => 'Street 1',
        'admin_city' => 'Ankara',
        'admin_state' => 'Ankara',
        'admin_zip' => '06000',
        'admin_country' => 'TR',
    ])->assertSessionHasNoErrors();
});
