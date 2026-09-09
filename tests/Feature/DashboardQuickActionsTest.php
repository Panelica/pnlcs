<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Ticket;

/**
 * The admin dashboard shows Quick Action shortcuts, each gated by the matching
 * permission. Assertions use the "+ Label" button rendering (unique to the
 * quick-actions card) so they don't collide with the nav/setup-checklist which
 * reuse the same words.
 */

it('shows quick action shortcuts on the dashboard for a full admin', function () {
    $admin = Admin::factory()->create(); // default factory role is full admin

    $this->actingAs($admin, 'admin')
        ->get('/admin')
        ->assertOk()
        ->assertSee('Quick Actions')
        ->assertSee('+ New Product')
        ->assertSee('+ Add Server')
        ->assertSee('+ Add Client')
        ->assertSee('+ New Invoice');
});

it('hides quick actions the admin lacks permission for', function () {
    $role  = AdminRole::factory()->create(['is_full_admin' => false, 'permissions' => ['create_clients']]);
    $admin = Admin::factory()->create(['role_id' => $role->id]);

    $this->actingAs($admin, 'admin')
        ->get('/admin')
        ->assertOk()
        ->assertSee('+ Add Client')        // has create_clients
        ->assertDontSee('+ New Product')   // lacks manage_products
        ->assertDontSee('+ Add Server')    // lacks manage_servers
        ->assertDontSee('+ New Invoice');  // lacks create_invoices
});

/*
 * The rest of the bar.
 *
 * The four "create" buttons above were only half of it: the screens that
 * manage what you just created were three clicks away down the sidebar, and an
 * operator still had to walk into orders, invoices and tickets one at a time to
 * find out whether any of them wanted something today.
 */
function quickActionsAdmin(array $permissions): Admin
{
    return Admin::factory()->create([
        'role_id' => AdminRole::factory()->create([
            'name' => 'Role '.uniqid(),
            'permissions' => $permissions,
        ])->id,
    ]);
}

test('it offers the management screens an admin is allowed to open', function () {
    $admin = quickActionsAdmin(['manage_gateways', 'manage_products', 'manage_servers', 'manage_domains']);

    $this->actingAs($admin, 'admin')->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee(__('admin.dashboard.payments'))
        ->assertSee(__('admin.dashboard.products'))
        ->assertSee(__('admin.dashboard.servers'))
        ->assertSee(__('admin.dashboard.domains'));
});

test('it offers nothing an admin may not open', function () {
    $admin = quickActionsAdmin(['list_tickets']);

    // The button itself, not the word: "Payments" and "Servers" appear in
    // plenty of other places on this page, and asserting on a word would pass
    // or fail for reasons that have nothing to do with the bar.
    $html = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'))->assertOk()->getContent();

    expect($html)->not->toContain('btn btn-default btn-sm">'.__('admin.dashboard.payments').'</a>')
        ->and($html)->not->toContain('btn btn-default btn-sm">'.__('admin.dashboard.servers').'</a>');
});

test('it counts what is actually waiting', function () {
    $client = Client::factory()->create();

    Order::factory()->count(2)->create(['client_id' => $client->id, 'status' => 'pending']);
    Order::factory()->create(['client_id' => $client->id, 'status' => 'active']);
    Invoice::factory()->count(3)->create(['client_id' => $client->id, 'status' => 'unpaid']);
    Ticket::factory()->create(['client_id' => $client->id, 'status' => 'open']);
    Ticket::factory()->create(['client_id' => $client->id, 'status' => 'closed']);

    $admin = quickActionsAdmin(['manage_orders', 'manage_invoices', 'list_tickets']);

    $html = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'))->assertOk()->getContent();

    expect($html)->toContain(__('admin.dashboard.needs_attention'))
        ->and($html)->toContain('<strong>2</strong> '.__('admin.dashboard.pending_orders'))
        ->and($html)->toContain('<strong>3</strong> '.__('admin.dashboard.unpaid_invoices'))
        ->and($html)->toContain('<strong>1</strong> '.__('admin.dashboard.open_tickets'));
});

test('a quiet day says nothing at all', function () {
    $admin = quickActionsAdmin(['manage_orders', 'manage_invoices', 'list_tickets']);

    $this->actingAs($admin, 'admin')->get(route('admin.dashboard'))
        ->assertOk()
        ->assertDontSee(__('admin.dashboard.needs_attention'));
});

test('an admin is not told about numbers they cannot look at', function () {
    $client = Client::factory()->create();
    Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid']);

    $admin = quickActionsAdmin(['list_tickets']);

    $html = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'))->assertOk()->getContent();

    expect($html)->not->toContain('<strong>1</strong> '.__('admin.dashboard.unpaid_invoices'));
});
