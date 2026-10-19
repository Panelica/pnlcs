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

/*
 * The billing summary's counts read $invoiceSummary, which nothing set: every
 * client showed 0 paid, 0 unpaid and 0 overdue invoices.
 */
test('the billing summary counts the client\'s invoices by status', function () {
    $client = Client::factory()->create();
    foreach (['paid', 'paid', 'unpaid', 'payment_pending', 'overdue', 'cancelled'] as $status) {
        Invoice::factory()->create(['client_id' => $client->id, 'status' => $status, 'total' => 10]);
    }
    Invoice::factory()->create(['status' => 'paid', 'total' => 10]); // someone else's

    $admin = Admin::factory()->create([
        'role_id' => AdminRole::factory()->create(['name' => 'Clients', 'permissions' => ['list_clients', 'view_clients']])->id,
    ]);

    $html = $this->actingAs($admin, 'admin')->get(route('admin.clients.show', $client))->assertOk()->getContent();

    $count = fn (string $label) => preg_match('#'.preg_quote(__($label), '#').'</td><td[^>]*>(\d+)</td>#', $html, $m) ? (int) $m[1] : null;

    expect($count('admin.clients.paid_invoices'))->toBe(2)
        ->and($count('admin.clients.unpaid_invoices'))->toBe(2)
        ->and($count('admin.clients.overdue_invoices'))->toBe(1);
});
