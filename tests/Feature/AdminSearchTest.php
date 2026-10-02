<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Domain;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\TicketDepartment;

/*
 * The admin bar's search finds every kind of record an admin may open.
 *
 * The box sent what was typed to the client list, so an invoice number, a
 * domain, a ticket or an order number found nothing.
 */

function asAdmin(array $permissions): Admin
{
    return Admin::factory()->create(['role_id' => AdminRole::factory()->create(['name' => 'Role '.uniqid(), 'permissions' => $permissions])->id]);
}

function asRecords(): array
{
    $client = Client::factory()->create(['first_name' => 'Ayşe', 'last_name' => 'Kaya', 'company_name' => null, 'email' => 'ayse@kaya.test']);

    return [
        'client' => $client,
        'invoice' => Invoice::factory()->create(['client_id' => $client->id, 'invoice_num' => 'INV-2026-0451']),
        'service' => Service::factory()->create(['client_id' => $client->id, 'domain' => 'kayahukuk.com']),
        'domain' => Domain::factory()->create(['client_id' => $client->id, 'domain' => 'kaya-shop.com']),
        'ticket' => Ticket::create(['tid' => '771204', 'client_id' => $client->id, 'department_id' => TicketDepartment::create(['name' => 'Support', 'hidden' => false, 'sort_order' => 1])->id,
            'name' => 'Ayşe Kaya', 'email' => 'ayse@kaya.test', 'title' => 'Mail bounces', 'message' => 'x', 'status' => 'Open', 'priority' => 'Medium']),
        'order' => Order::create(['order_num' => '9988776655', 'client_id' => $client->id, 'date' => now(), 'amount' => 10, 'status' => 'Pending']),
    ];
}

/** @return array<string, list<string>> titles by kind */
function asSearch(Admin $admin, string $q): array
{
    $groups = test()->actingAs($admin, 'admin')->getJson(route('admin.search', ['q' => $q]))->assertOk()->json('groups');

    return collect($groups)->mapWithKeys(fn ($g) => [$g['type'] => array_column($g['items'], 'title')])->all();
}

const AS_ALL = ['view_clients', 'view_invoices', 'view_services', 'manage_domains', 'view_tickets', 'view_orders'];

it('finds each kind of record by what an admin would type', function () {
    $r = asRecords();
    $admin = asAdmin(AS_ALL);

    expect(asSearch($admin, 'Ayşe Kaya')['clients'][0])->toContain('Ayşe Kaya')
        ->and(asSearch($admin, '0451')['invoices'][0])->toContain('INV-2026-0451')
        ->and(asSearch($admin, 'kayahukuk')['services'][0])->toContain('kayahukuk.com')
        ->and(asSearch($admin, 'kaya-shop')['domains'][0])->toBe('kaya-shop.com')
        ->and(asSearch($admin, '#771204')['tickets'][0])->toContain('Mail bounces')
        ->and(asSearch($admin, '9988776655')['orders'][0])->toContain('9988776655');
});

it('links each result to its page', function () {
    $r = asRecords();
    $groups = test()->actingAs(asAdmin(AS_ALL), 'admin')->getJson(route('admin.search', ['q' => 'kaya-shop']))->json('groups');

    expect($groups[0]['items'][0]['url'])->toBe(route('admin.domains.show', $r['domain']));
});

it('only searches what the admin may open', function () {
    asRecords();
    $billing = asAdmin(['view_invoices']);

    $found = asSearch($billing, 'kaya');
    expect(array_keys($found))->toBe([])
        ->and(array_keys(asSearch($billing, '0451')))->toBe(['invoices']);

    expect(array_keys(asSearch(asAdmin([]), 'kaya')))->toBe([]);
});

it('ignores a query that is too short and treats wildcards as text', function () {
    asRecords();
    $admin = asAdmin(AS_ALL);

    expect(asSearch($admin, 'k'))->toBe([])
        ->and(asSearch($admin, '%%'))->toBe([])
        ->and(asSearch($admin, '__'))->toBe([]);
});

it('keeps the search behind the admin sign-in and shows the box\'s results list', function () {
    test()->getJson(route('admin.search', ['q' => 'kaya']))->assertUnauthorized();

    test()->actingAs(asAdmin(AS_ALL), 'admin')->get(route('admin.dashboard'))
        ->assertOk()->assertSee('id="intellisearch-results"', false)->assertSee(json_encode(route('admin.search')), false);
});
