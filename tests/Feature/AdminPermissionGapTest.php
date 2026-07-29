<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Invoice;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Mail;

/**
 * Restricted staff and the pages nobody put a permission on.
 *
 * 131 of the admin config routes require a permission. A handful did not, and
 * two of those show the same data as pages that are guarded elsewhere: the
 * payment ledger and the affiliate list. A support agent with ticket
 * permissions could read every payment the business has ever taken by typing
 * the URL.
 */
function supportOnlyAdmin(): Admin
{
    $role = AdminRole::factory()->create([
        'name' => 'Support',
        'permissions' => ['list_tickets', 'view_tickets', 'reply_tickets'],
    ]);

    return Admin::factory()->create(['role_id' => $role->id]);
}

test('a support agent cannot read the payment ledger', function () {
    Mail::fake();

    // Some money on the books to leak.
    $client = Client::factory()->create(['first_name' => 'Paying', 'last_name' => 'Customer']);
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => 500]);
    app(PaymentService::class)->applyPayment($invoice, 'stripe', 'TXN-LEAK', 500.0);

    $this->actingAs(supportOnlyAdmin(), 'admin')
        ->get(route('admin.config.transactions'))
        ->assertForbidden();
});

test('a support agent cannot read the affiliate list', function () {
    $this->actingAs(supportOnlyAdmin(), 'admin')
        ->get(route('admin.config.affiliates'))
        ->assertForbidden();
});

test('a support agent cannot read the quote settings', function () {
    $this->actingAs(supportOnlyAdmin(), 'admin')
        ->get(route('admin.config.quotes'))
        ->assertForbidden();
});

test('a support agent cannot read the automation settings', function () {
    $this->actingAs(supportOnlyAdmin(), 'admin')
        ->get(route('admin.config.automation'))
        ->assertForbidden();
});

test('an admin with the right permissions still gets in', function () {
    Mail::fake();

    $role = AdminRole::factory()->create([
        'name' => 'Finance',
        'permissions' => ['view_reports', 'manage_affiliates', 'manage_quotes', 'manage_settings'],
    ]);
    $admin = Admin::factory()->create(['role_id' => $role->id]);

    $blocked = [];

    foreach ([
        'transactions' => route('admin.config.transactions'),
        'affiliates' => route('admin.config.affiliates'),
        'quotes' => route('admin.config.quotes'),
        'automation' => route('admin.config.automation'),
    ] as $label => $url) {
        $status = $this->actingAs($admin, 'admin')->get($url)->status();

        if ($status !== 200) {
            $blocked[] = $label.' → HTTP '.$status;
        }
    }

    expect($blocked)->toBe([]);
});

test('a full admin is unaffected', function () {
    $this->actingAs(Admin::factory()->create(), 'admin')
        ->get(route('admin.config.transactions'))
        ->assertOk();
});
