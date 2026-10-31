<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\ApiCredential;
use App\Models\Client;
use App\Support\ApiPermissionMap;
use Illuminate\Support\Str;

/*
 * An API call needs the permission the panel asks for the same thing. These
 * calls were placed by their verb ("add…", a read, "…reply") and landed on a
 * lower one than the panel's.
 */

test('the call needs the permission the panel asks for', function (string $controller, string $method, string $action, string $permission) {
    expect(ApiPermissionMap::required($controller, $method, $action))->toBe($permission);
})->with([
    'record a payment' => ['InvoiceApiController', 'POST', 'addinvoicepayment', 'manage_invoices'],
    'record a transaction' => ['InvoiceApiController', 'POST', 'addtransaction', 'manage_invoices'],
    'add credit' => ['ClientApiController', 'POST', 'addcredit', 'manage_invoices'],
    'read a transfer code' => ['DomainApiController', 'GET', 'domainrequestepp', 'manage_domains'],
    'read registrant details' => ['DomainApiController', 'GET', 'domaingetwhoisinfo', 'manage_domains'],
    'read sent mail' => ['SystemApiController', 'GET', 'getemails', 'view_activity_log'],
    'edit a ticket message' => ['TicketApiController', 'POST', 'updateticketreply', 'manage_tickets'],
    'delete a ticket message' => ['TicketApiController', 'POST', 'deleteticketreply', 'manage_tickets'],
    'add a client note' => ['ClientApiController', 'POST', 'addclientnote', 'edit_clients'],
    'add a contact' => ['ClientApiController', 'POST', 'addcontact', 'edit_clients'],
    'add a login' => ['ClientApiController', 'POST', 'adduser', 'edit_clients'],
]);

test('the calls next to them are unchanged', function (string $controller, string $method, string $action, string $permission) {
    expect(ApiPermissionMap::required($controller, $method, $action))->toBe($permission);
})->with([
    'add a reply' => ['TicketApiController', 'POST', 'addticketreply', 'reply_tickets'],
    'create an invoice' => ['InvoiceApiController', 'POST', 'createinvoice', 'create_invoices'],
    'create a client' => ['ClientApiController', 'POST', 'addclient', 'create_clients'],
    'read nameservers' => ['DomainApiController', 'GET', 'domaingetnameservers', 'list_domains'],
]);

function awmpCredential(array $permissions): array
{
    $admin = Admin::factory()->create([
        'role_id' => AdminRole::factory()->create(['is_full_admin' => false, 'permissions' => $permissions])->id,
    ]);
    $secret = Str::random(64);
    $credential = ApiCredential::create([
        'admin_id' => $admin->id, 'identifier' => Str::random(32), 'secret' => ApiCredential::hashSecret($secret),
        'description' => 'test', 'active' => true,
    ]);

    return ['api_key' => $credential->identifier, 'api_secret' => $secret];
}

test('staff who may create and edit clients cannot give one credit', function () {
    $client = Client::factory()->create(['credit' => 0]);

    $this->postJson('/api/v1/addcredit', awmpCredential(['create_clients', 'edit_clients', 'list_clients']) + [
        'clientid' => $client->id, 'amount' => 500, 'description' => 'x',
    ])->assertForbidden();

    expect((float) $client->fresh()->credit)->toBe(0.0);
});

test('staff who manage invoices still can', function () {
    $client = Client::factory()->create(['credit' => 0]);

    $this->postJson('/api/v1/addcredit', awmpCredential(['manage_invoices']) + [
        'clientid' => $client->id, 'amount' => 50, 'description' => 'goodwill',
    ])->assertOk();

    expect((float) $client->fresh()->credit)->toBe(50.0);
});
