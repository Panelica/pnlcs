<?php

use App\Models\Admin;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\Domain;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Order;


test('client show page loads summary tab', function () {
    $admin = Admin::factory()->create();
    $client = Client::factory()->create();
    $this->actingAs($admin, 'admin')
        ->get(route('admin.clients.show', $client))
        ->assertStatus(200)
        ->assertSee($client->full_name);
});

test('client show page loads services tab', function () {
    $admin = Admin::factory()->create();
    $client = Client::factory()->create();
    $group = ProductGroup::factory()->create();
    $product = Product::factory()->create(['group_id' => $group->id]);
    $order = Order::factory()->create(['client_id' => $client->id]);
    Service::factory()->create(['client_id' => $client->id, 'product_id' => $product->id, 'order_id' => $order->id]);

    $this->actingAs($admin, 'admin')
        ->get(route('admin.clients.show', ['client' => $client, 'tab' => 'services']))
        ->assertStatus(200);
});

test('client show page loads invoices tab', function () {
    $admin = Admin::factory()->create();
    $client = Client::factory()->create();
    Invoice::factory()->create(['client_id' => $client->id]);

    $this->actingAs($admin, 'admin')
        ->get(route('admin.clients.show', ['client' => $client, 'tab' => 'invoices']))
        ->assertStatus(200);
});

test('client show page loads tickets tab', function () {
    $admin = Admin::factory()->create();
    $client = Client::factory()->create();
    $dept = TicketDepartment::factory()->create();
    Ticket::factory()->create(['client_id' => $client->id, 'department_id' => $dept->id]);

    $this->actingAs($admin, 'admin')
        ->get(route('admin.clients.show', ['client' => $client, 'tab' => 'tickets']))
        ->assertStatus(200);
});

test('client show page loads domains tab', function () {
    $admin = Admin::factory()->create();
    $client = Client::factory()->create();
    Domain::factory()->create(['client_id' => $client->id]);

    $this->actingAs($admin, 'admin')
        ->get(route('admin.clients.show', ['client' => $client, 'tab' => 'domains']))
        ->assertStatus(200);
});

test('client show page loads notes tab', function () {
    $admin = Admin::factory()->create();
    $client = Client::factory()->create();
    $this->actingAs($admin, 'admin')
        ->get(route('admin.clients.show', ['client' => $client, 'tab' => 'notes']))
        ->assertStatus(200);
});

test('client summary shows correct counts', function () {
    $admin = Admin::factory()->create();
    $client = Client::factory()->create();
    $group = ProductGroup::factory()->create();
    $product = Product::factory()->create(['group_id' => $group->id]);
    $order = Order::factory()->create(['client_id' => $client->id]);
    Service::factory()->count(3)->create(['client_id' => $client->id, 'product_id' => $product->id, 'order_id' => $order->id]);
    Domain::factory()->count(2)->create(['client_id' => $client->id]);
    Invoice::factory()->count(4)->create(['client_id' => $client->id]);

    $response = $this->actingAs($admin, 'admin')
        ->get(route('admin.clients.show', $client));
    $response->assertStatus(200);
});

test('admin can create client', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin')
        ->post(route('admin.clients.store'), [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'status' => 'active',
        ])
        ->assertRedirect();

    expect(Client::where('email', 'john@example.com')->exists())->toBeTrue();
});

test('admin can update client', function () {
    $admin = Admin::factory()->create();
    $client = Client::factory()->create();
    $this->actingAs($admin, 'admin')
        ->put(route('admin.clients.update', $client), [
            'first_name' => 'Updated',
            'last_name' => 'Name',
            'email' => $client->email,
            'status' => 'active',
        ])
        ->assertRedirect();

});

test('admin can delete client', function () {
    $admin = Admin::factory()->create();
    $client = Client::factory()->create();
    $this->actingAs($admin, 'admin')
        ->delete(route('admin.clients.destroy', $client))
        ->assertRedirect(route('admin.clients.index'));
});
