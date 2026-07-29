<?php

use App\Models\Admin;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Models\Transaction;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Modules\Reports\IncomeSummaryReport;

/**
 * Deleting a customer.
 *
 * The delete went through with no questions asked, and the cascade took the
 * services with it. The accounts themselves were never terminated on the
 * control panel, so the hosting carried on running with nothing left in the
 * panel to say it existed, who it belonged to, or that it should be stopped.
 */
function clientWithService(string $status = 'active'): array
{
    $client = Client::factory()->create();
    $product = Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id]);

    $service = Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'status' => $status,
        'domain' => 'still-running.com',
        'amount' => 20,
    ]);

    return compact('client', 'service');
}

test('a customer with a live account cannot simply be deleted', function () {
    $admin = Admin::factory()->create();
    $fx = clientWithService('active');

    $this->actingAs($admin, 'admin')
        ->delete(route('admin.clients.destroy', $fx['client']))
        ->assertRedirect();

    expect(Client::find($fx['client']->id))->not->toBeNull()
        ->and(Service::find($fx['service']->id))->not->toBeNull();
});

test('a suspended account also counts as live', function () {
    $admin = Admin::factory()->create();
    $fx = clientWithService('suspended');

    $this->actingAs($admin, 'admin')
        ->delete(route('admin.clients.destroy', $fx['client']))
        ->assertRedirect();

    expect(Client::find($fx['client']->id))->not->toBeNull();
});

test('once the accounts are terminated the customer can be deleted', function () {
    $admin = Admin::factory()->create();
    $fx = clientWithService('terminated');

    $this->actingAs($admin, 'admin')
        ->delete(route('admin.clients.destroy', $fx['client']))
        ->assertRedirect();

    expect(Client::find($fx['client']->id))->toBeNull();
});

test('a customer with nothing attached can be deleted', function () {
    $admin = Admin::factory()->create();
    $client = Client::factory()->create();

    $this->actingAs($admin, 'admin')
        ->delete(route('admin.clients.destroy', $client))
        ->assertRedirect();

    expect(Client::find($client->id))->toBeNull();
});

test('deleting a customer still clears their invoices', function () {
    $admin = Admin::factory()->create();
    $client = Client::factory()->create();
    $invoice = Invoice::factory()->create(['client_id' => $client->id]);

    $this->actingAs($admin, 'admin')
        ->delete(route('admin.clients.destroy', $client))
        ->assertRedirect();

    expect(Invoice::find($invoice->id))->toBeNull();
});

test('deleting a customer keeps the record of money that moved', function () {
    $admin = Admin::factory()->create();
    $client = Client::factory()->create(['first_name' => 'Deleted', 'last_name' => 'Customer', 'email' => 'gone@example.com']);

    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => 90]);
    app(PaymentService::class)->applyPayment($invoice, 'banktransfer', 'TXN-KEEP', 90.0);

    $txnId = Transaction::where('transaction_id', 'TXN-KEEP')->firstOrFail()->id;

    $this->actingAs($admin, 'admin')
        ->delete(route('admin.clients.destroy', $client))
        ->assertRedirect();

    expect(Client::find($client->id))->toBeNull();

    $txn = Transaction::find($txnId);

    // The client row is soft deleted, so the cascade never fires and the
    // ledger keeps both the money and who it came from. This is what stops
    // last year reported revenue changing because someone tidied up the
    // client list.
    expect($txn)->not->toBeNull()
        ->and((float) $txn->amount_in)->toBe(90.0)
        ->and($txn->client_id)->toBe($client->id)
        ->and(Client::withTrashed()->find($client->id))->not->toBeNull();
});

test('the income summary still counts a deleted customer payments', function () {
    $admin = Admin::factory()->create();
    $client = Client::factory()->create();
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => 250]);
    app(PaymentService::class)->applyPayment($invoice, 'banktransfer', 'TXN-HIST', 250.0);

    $this->actingAs($admin, 'admin')->delete(route('admin.clients.destroy', $client))->assertRedirect();

    $report = (new IncomeSummaryReport)->generate(new Request);

    // Revenue reported for a past month must not change because someone
    // tidied up the client list.
    expect((float) $report['totals'][1])->toBe(250.0);
});
