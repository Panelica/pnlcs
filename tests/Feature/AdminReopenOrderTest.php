<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Order;

/**
 * A cancelled or fraud order could be put back to pending through the API
 * (pendingorder) but not from the admin, so an order held by fraud screening
 * by mistake could not be released there.
 */
function reopenAdmin(array $permissions): Admin
{
    return Admin::factory()->create(['role_id' => AdminRole::factory()->create(['name' => 'R'.uniqid(), 'permissions' => $permissions])->id]);
}

function heldOrder(string $status): Order
{
    return Order::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'status' => $status,
        'order_num' => strtoupper(substr(md5($status.microtime()), 0, 8)),
    ]);
}

it('puts a held order back to pending', function (string $status) {
    $order = heldOrder($status);

    $this->actingAs(reopenAdmin(['list_orders', 'view_orders', 'manage_orders']), 'admin')
        ->post(route('admin.orders.reopen', $order))
        ->assertSessionHas('success');

    expect($order->fresh()->status)->toBe('pending');
})->with(['fraud', 'cancelled']);

it('leaves an active order alone', function () {
    $order = heldOrder('active');

    $this->actingAs(reopenAdmin(['list_orders', 'view_orders', 'manage_orders']), 'admin')
        ->post(route('admin.orders.reopen', $order));

    expect($order->fresh()->status)->toBe('active');
});

it('needs the manage orders permission', function () {
    $order = heldOrder('fraud');

    $this->actingAs(reopenAdmin(['list_orders', 'view_orders']), 'admin')
        ->post(route('admin.orders.reopen', $order))
        ->assertForbidden();

    expect($order->fresh()->status)->toBe('fraud');
});

it('shows the button on a fraud order only', function () {
    $admin = reopenAdmin(['list_orders', 'view_orders', 'manage_orders']);
    $fraud = heldOrder('fraud');
    $pending = heldOrder('pending');

    $this->actingAs($admin, 'admin')->get(route('admin.orders.show', $fraud))
        ->assertOk()->assertSee(route('admin.orders.reopen', $fraud));
    $this->actingAs($admin, 'admin')->get(route('admin.orders.show', $pending))
        ->assertOk()->assertDontSee(route('admin.orders.reopen', $pending));
});
