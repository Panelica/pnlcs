<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;

it('offers invoice creation from the client invoices tab', function () {
    $client = Client::factory()->create();

    $this->actingAs(Admin::factory()->create(), 'admin')
        ->get(route('admin.clients.show', ['client' => $client, 'tab' => 'invoices']))
        ->assertOk()
        ->assertSee(__('admin.clients.add_invoice'))
        ->assertSee('href="'.route('admin.invoices.create', ['client_id' => $client->id]).'"', false);
});

it('hides the invoice button without create_invoices permission', function () {
    $client = Client::factory()->create();
    $role = AdminRole::factory()->create([
        'name' => 'Client viewer '.uniqid(),
        'permissions' => ['view_clients'],
    ]);

    $this->actingAs(Admin::factory()->create(['role_id' => $role->id]), 'admin')
        ->get(route('admin.clients.show', ['client' => $client, 'tab' => 'invoices']))
        ->assertOk()
        ->assertDontSee(__('admin.clients.add_invoice'));
});

it('preselects the client on the invoice create form', function () {
    $client = Client::factory()->create();

    $this->actingAs(Admin::factory()->create(), 'admin')
        ->get(route('admin.invoices.create', ['client_id' => $client->id]))
        ->assertOk()
        ->assertSee('value="'.$client->id.'"', false)
        ->assertSee('selected', false);
});
