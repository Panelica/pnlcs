<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Domain;
use App\Models\Email;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Service;
use App\Models\SslOrder;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use App\Models\User;
use App\Models\UserLogin;

/*
 * A customer's copy of their personal data (GDPR Art. 15 / 20, KVKK Art. 11).
 *
 * Nothing produced one. A customer who asked had to wait for someone to copy
 * screens by hand. The file names each column it carries, so secrets stay out.
 */

function pdeAccount(): array
{
    $client = Client::factory()->create([
        'first_name' => 'Ayşe', 'last_name' => 'Yılmaz', 'email' => 'ayse@example.test',
        'notes' => 'INTERNAL-NOTE-do-not-share',
    ]);
    $owner = User::factory()->create(['email' => 'ayse@example.test']);
    $client->users()->attach($owner->id, ['owner' => true]);
    UserLogin::create(['user_id' => $owner->id, 'successful' => true, 'method' => 'password', 'ip_address' => '198.51.100.7',
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:131.0) Gecko/20100101 Firefox/131.0', 'device' => 'DEVICEHASH-'.str_repeat('a', 53)]);

    Service::factory()->create(['client_id' => $client->id, 'domain' => 'ayse.example', 'username' => 'ayseu', 'password' => 'SERVICE-SECRET-123']);
    Domain::factory()->create(['client_id' => $client->id, 'domain' => 'ayse.example', 'epp_code' => 'EPP-SECRET-456']);
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'total' => 120]);
    InvoiceItem::create(['invoice_id' => $invoice->id, 'client_id' => $client->id, 'type' => 'Hosting', 'description' => 'Hosting Small', 'amount' => 120]);
    Ticket::create(['tid' => 'ABC-123', 'client_id' => $client->id, 'department_id' => TicketDepartment::create(['name' => 'Support', 'hidden' => false, 'sort_order' => 1])->id,
        'name' => 'Ayşe', 'email' => 'ayse@example.test', 'title' => 'Help with mail', 'message' => 'My mail is down', 'status' => 'Open', 'priority' => 'Medium']);
    Email::create(['client_id' => $client->id, 'subject' => 'Your hosting is ready', 'message' => 'Panel password: WELCOME-PW-321', 'date' => now(), 'to' => 'ayse@example.test']);
    SslOrder::create(['client_id' => $client->id, 'module' => 'manual', 'domain' => 'ayse.example', 'status' => 'Active', 'private_key' => 'PRIVATE-KEY-789', 'csr' => 'CSR-000']);

    return [$client, $owner];
}

it('lets the account owner download everything as JSON', function () {
    [$client, $owner] = pdeAccount();

    $response = test()->actingAs($owner)->post(route('client.account.personal-data'))->assertOk();

    expect($response->headers->get('Content-Disposition'))->toContain('attachment')
        ->and($response->headers->get('Content-Type'))->toContain('application/json');
    $data = $response->json();
    expect($data['account']['first_name'])->toBe('Ayşe')
        ->and($data['logins'][0]['email'])->toBe('ayse@example.test')
        ->and($data['logins'][0]['sign_ins'][0]['ip_address'])->toBe('198.51.100.7')
        ->and($data['logins'][0]['sign_ins'][0]['device'])->toBe('Firefox on Windows')
        ->and($data['services'][0]['domain'])->toBe('ayse.example')
        ->and($data['domains'][0]['domain'])->toBe('ayse.example')
        ->and($data['invoices'][0]['items'][0]['description'])->toBe('Hosting Small')
        ->and($data['tickets'][0]['title'])->toBe('Help with mail')
        ->and($data['ssl_certificates'][0]['domain'])->toBe('ayse.example')
        ->and($data['emails_sent'][0]['subject'])->toBe('Your hosting is ready');
});

it('leaves passwords, transfer codes, keys and internal notes out', function () {
    [$client, $owner] = pdeAccount();

    $raw = test()->actingAs($owner)->post(route('client.account.personal-data'))->getContent();

    foreach (['DEVICEHASH-', 'WELCOME-PW-321', 'SERVICE-SECRET-123', 'EPP-SECRET-456', 'PRIVATE-KEY-789', 'CSR-000', 'INTERNAL-NOTE-do-not-share', '"password":', 'second_factor', 'remember_token'] as $secret) {
        expect($raw)->not->toContain($secret);
    }
});

it('keeps the download to the account owner', function () {
    [$client] = pdeAccount();
    $member = User::factory()->create();
    $client->users()->attach($member->id, ['owner' => false, 'permissions' => json_encode(['invoices', 'profile'])]);

    test()->actingAs($member)->post(route('client.account.personal-data'))->assertForbidden();
    test()->actingAs($member)->get(route('client.account.security'))->assertOk()->assertDontSee(route('client.account.personal-data'), false);
});

it('lets an admin with the client permission download it for a request that came by email', function () {
    [$client] = pdeAccount();
    $viewer = Admin::factory()->create(['role_id' => AdminRole::factory()->create(['name' => 'Support', 'permissions' => ['view_clients', 'list_clients']])->id]);
    $outsider = Admin::factory()->create(['role_id' => AdminRole::factory()->create(['name' => 'Billing', 'permissions' => ['list_invoices']])->id]);

    test()->actingAs($viewer, 'admin')->get(route('admin.clients.show', $client))->assertSee(route('admin.clients.personal-data', $client), false);
    $data = test()->actingAs($viewer, 'admin')->post(route('admin.clients.personal-data', $client))->assertOk()->json();
    expect($data['account']['email'])->toBe('ayse@example.test');

    test()->actingAs($outsider, 'admin')->post(route('admin.clients.personal-data', $client))->assertForbidden();
});

it('includes only what belongs to the account', function () {
    [$client, $owner] = pdeAccount();
    [$other] = (function () {
        $c = Client::factory()->create(['email' => 'other@example.test']);
        Domain::factory()->create(['client_id' => $c->id, 'domain' => 'someone-else.example']);

        return [$c];
    })();

    $raw = test()->actingAs($owner)->post(route('client.account.personal-data'))->getContent();
    expect($raw)->not->toContain('someone-else.example')->not->toContain('other@example.test');
});
