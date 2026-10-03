<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Order;

/*
 * Orders could only be accepted or cancelled one at a time, from each order's
 * own page. After a weekend, or a campaign, that is a page per order. The
 * invoice list already had bulk actions; the order list had none.
 */

function bulkOrderAdmin(array $permissions = ['list_orders', 'view_orders', 'manage_orders']): Admin
{
    return Admin::factory()->create(['role_id' => AdminRole::factory()->create(['name' => 'R'.uniqid(), 'permissions' => $permissions])->id]);
}

function bulkOrder(string $status): Order
{
    return Order::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'status' => $status,
        'order_num' => strtoupper(substr(md5($status.microtime()), 0, 8)),
    ]);
}

test('several pending orders are accepted at once, and the others are left alone', function () {
    [$a, $b] = [bulkOrder('pending'), bulkOrder('pending')];
    $cancelled = bulkOrder('cancelled');

    $this->actingAs(bulkOrderAdmin(), 'admin')->from(route('admin.orders.index'))
        ->post(route('admin.orders.bulk'), ['action' => 'accept', 'order_ids' => [$a->id, $b->id, $cancelled->id]])
        ->assertRedirect(route('admin.orders.index'))
        ->assertSessionHas('success', __('admin.orders.bulk_done_accept', ['done' => 2, 'skipped' => 1]));

    expect($a->fresh()->status)->toBe('active')
        ->and($b->fresh()->status)->toBe('active')
        ->and($cancelled->fresh()->status)->toBe('cancelled');
});

test('several orders are cancelled at once, but not one already marked fraud', function () {
    [$pending, $active] = [bulkOrder('pending'), bulkOrder('active')];
    $fraud = bulkOrder('fraud');

    $this->actingAs(bulkOrderAdmin(), 'admin')->from(route('admin.orders.index'))
        ->post(route('admin.orders.bulk'), ['action' => 'cancel', 'order_ids' => [$pending->id, $active->id, $fraud->id]])
        ->assertSessionHas('success', __('admin.orders.bulk_done_cancel', ['done' => 2, 'skipped' => 1]));

    expect($pending->fresh()->status)->toBe('cancelled')
        ->and($active->fresh()->status)->toBe('cancelled')
        ->and($fraud->fresh()->status)->toBe('fraud');
});

test('the list offers the actions only to staff who may manage orders', function () {
    bulkOrder('pending');

    $this->actingAs(bulkOrderAdmin(), 'admin')->get(route('admin.orders.index'))
        ->assertOk()->assertSee('order-row-checkbox', false)->assertSee(route('admin.orders.bulk'), false)->assertSee('data-action="accept"', false);

    $this->actingAs(bulkOrderAdmin(['list_orders', 'view_orders']), 'admin')->get(route('admin.orders.index'))
        ->assertOk()->assertDontSee('data-action="accept"', false);
    $this->actingAs(bulkOrderAdmin(['list_orders', 'view_orders']), 'admin')
        ->post(route('admin.orders.bulk'), ['action' => 'accept', 'order_ids' => [1]])->assertForbidden();
});
