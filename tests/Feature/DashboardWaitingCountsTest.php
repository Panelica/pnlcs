<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Invoice;

test('the invoices waiting on the dashboard include overdue and part-paid ones', function () {
    $client = Client::factory()->create();
    foreach (['unpaid', 'overdue', 'partially_paid', 'payment_pending', 'paid', 'cancelled'] as $status) {
        Invoice::factory()->create(['client_id' => $client->id, 'status' => $status, 'total' => 10]);
    }
    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);

    $waiting = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'))->assertOk()->viewData('waiting');

    expect($waiting['invoices'])->toBe(4);
});
