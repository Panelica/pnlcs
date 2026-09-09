<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Invoice;
use App\Services\PaymentService;

/*
 * The "unpaid" figure on a customer's page counted invoices whose status was
 * literally unpaid. The overdue ones - the money the operator most wants to
 * see - and the remainder of a part-paid invoice did not count.
 */
test('the unpaid figure on the client page counts overdue and part-paid invoices', function () {
    $client = Client::factory()->create();
    Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => 10]);
    Invoice::factory()->create(['client_id' => $client->id, 'status' => 'overdue', 'total' => 20]);
    $partial = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => 50]);
    app(PaymentService::class)->applyPayment($partial, 'banktransfer', 'tx-p', 30.0);   // 20 left
    Invoice::factory()->create(['client_id' => $client->id, 'status' => 'paid', 'total' => 999]);
    Invoice::factory()->create(['client_id' => $client->id, 'status' => 'cancelled', 'total' => 777]);

    $admin = Admin::factory()->create([
        'role_id' => AdminRole::factory()->create(['name' => 'Clients', 'permissions' => ['list_clients', 'view_clients']])->id,
    ]);

    $this->actingAs($admin, 'admin')->get(route('admin.clients.show', $client))
        ->assertOk()
        ->assertSee(money_fmt(50), false);
});
