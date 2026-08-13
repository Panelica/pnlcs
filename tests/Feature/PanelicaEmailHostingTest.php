<?php

use App\Models\Client;
use App\Models\Product;
use App\Models\Server;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Modules\Servers\Panelica\PanelicaModule;

/*
 * Phase 1 of the Panelica client hosting area: email accounts.
 *
 * The server API key PNLCS holds is operator-scoped, so ownership is not the
 * panel's job here - it is the module's. Every mutation is fenced to the
 * account's own domains/mailboxes before a request is sent. These tests pin
 * that fence, the plan-limit surfacing, and the controller's client-ownership
 * gate. They are also the guard rail for the phases that follow (dns, files,
 * databases, ...), which reuse the same accountDomains() fence.
 */

function panelServerE(): Server
{
    return Server::create([
        'name' => 'Panel', 'hostname' => 'panel.test', 'ip_address' => '10.0.0.9',
        'type' => 'panelica', 'username' => 'u', 'password' => 'pk', 'access_hash' => 'sk',
        'port' => 8443, 'active' => true,
    ]);
}

/** A provisioned Panelica service owned by a fresh client/user. */
function emailService(Server $server, string $accountId = 'acct-1'): array
{
    $client = Client::factory()->create();
    $user = User::factory()->create();
    $user->clients()->attach($client->id);
    $product = Product::factory()->create(['server_type' => 'panelica']);
    $service = Service::factory()->create([
        'client_id' => $client->id, 'product_id' => $product->id, 'server_id' => $server->id,
        'status' => 'active', 'module_data' => ['panelica_user_id' => $accountId],
    ]);

    return [$user, $service, $client];
}

/**
 * Fake the external API by method+path. The account owns one domain (dom-own);
 * dom-foreign belongs to someone else and must never be honoured.
 */
function fakePanelEmailApi(array $emails): void
{
    Http::fake(function ($request) use ($emails) {
        $url = $request->url();
        $method = $request->method();

        if (str_contains($url, '/v1/accounts/') && str_contains($url, '/domains')) {
            return Http::response(['data' => [
                ['id' => 'dom-own', 'domain_name' => 'example.com'],
            ]], 200);
        }
        if (str_contains($url, '/v1/email-accounts') && $method === 'GET') {
            return Http::response(['data' => $emails], 200);
        }
        if (str_contains($url, '/change-password') && $method === 'POST') {
            return Http::response(['status' => 'success'], 200);
        }
        if (str_contains($url, '/v1/email-accounts') && $method === 'POST') {
            return Http::response(['status' => 'success', 'data' => ['id' => 'new-mail']], 201);
        }
        if (str_contains($url, '/v1/email-accounts') && $method === 'DELETE') {
            return Http::response(['status' => 'success'], 200);
        }

        return Http::response(['data' => []], 200);
    });
}

// ---------------------------------------------------------------------------
// Feature declaration
// ---------------------------------------------------------------------------

it('offers the email feature only for a provisioned service', function () {
    $server = panelServerE();
    [$user, $service] = emailService($server);
    expect((new PanelicaModule)->hostingFeatures($service))->toBe(['emails']);

    $blank = Service::factory()->create([
        'client_id' => $service->client_id, 'server_id' => $server->id,
        'status' => 'active', 'module_data' => [],
    ]);
    expect((new PanelicaModule)->hostingFeatures($blank))->toBe([]);
});

// ---------------------------------------------------------------------------
// Read fence: a mailbox on a domain the account does not own is hidden
// ---------------------------------------------------------------------------

it('lists only mailboxes on the account\'s own domains', function () {
    fakePanelEmailApi([
        ['id' => 'm1', 'email' => 'info@example.com', 'domain_id' => 'dom-own', 'quota_mb' => 1024, 'used_quota_mb' => 10, 'status' => 'active'],
        ['id' => 'm2', 'email' => 'ceo@rival.com', 'domain_id' => 'dom-foreign', 'quota_mb' => 2048, 'used_quota_mb' => 5, 'status' => 'active'],
    ]);
    [$user, $service] = emailService(panelServerE());

    $emails = (new PanelicaModule)->listEmails($service);

    expect($emails)->toHaveCount(1)
        ->and($emails[0]['email'])->toBe('info@example.com')
        ->and($emails[0]['domain'])->toBe('example.com');
});

// ---------------------------------------------------------------------------
// Write fence: create/delete/password refuse a foreign target, send nothing
// ---------------------------------------------------------------------------

it('refuses to create a mailbox on a domain the account does not own', function () {
    fakePanelEmailApi([]);
    [$user, $service] = emailService(panelServerE());

    $result = (new PanelicaModule)->createEmail($service, 'dom-foreign', 'x', 'password123');

    expect($result['success'])->toBeFalse();
    Http::assertNotSent(fn ($r) => $r->method() === 'POST' && str_contains($r->url(), '/v1/email-accounts'));
});

it('refuses to delete a mailbox that is not one of the account\'s own', function () {
    fakePanelEmailApi([
        ['id' => 'm1', 'email' => 'info@example.com', 'domain_id' => 'dom-own', 'quota_mb' => 0, 'used_quota_mb' => 0, 'status' => 'active'],
    ]);
    [$user, $service] = emailService(panelServerE());

    $result = (new PanelicaModule)->deleteEmail($service, 'm-someone-else');

    expect($result['success'])->toBeFalse();
    Http::assertNotSent(fn ($r) => $r->method() === 'DELETE');
});

it('refuses to change the password of a mailbox that is not the account\'s own', function () {
    fakePanelEmailApi([
        ['id' => 'm1', 'email' => 'info@example.com', 'domain_id' => 'dom-own', 'quota_mb' => 0, 'used_quota_mb' => 0, 'status' => 'active'],
    ]);
    [$user, $service] = emailService(panelServerE());

    $result = (new PanelicaModule)->changeEmailPassword($service, 'm-someone-else', 'password123');

    expect($result['success'])->toBeFalse();
    Http::assertNotSent(fn ($r) => str_contains($r->url(), '/change-password'));
});

// ---------------------------------------------------------------------------
// Happy path + plan limit
// ---------------------------------------------------------------------------

it('creates a mailbox on an owned domain and sends the right payload', function () {
    fakePanelEmailApi([]);
    [$user, $service] = emailService(panelServerE());

    $result = (new PanelicaModule)->createEmail($service, 'dom-own', 'Info', 'password123', 500);

    expect($result['success'])->toBeTrue()
        ->and($result['data']['email'])->toBe('info@example.com');

    Http::assertSent(function ($r) {
        if ($r->method() !== 'POST' || ! str_contains($r->url(), '/v1/email-accounts')) {
            return false;
        }
        $body = json_decode($r->body(), true);

        return ($body['domain_id'] ?? null) === 'dom-own'
            && ($body['username'] ?? null) === 'info'
            && ($body['quota_mb'] ?? null) === 500;
    });
});

it('surfaces the plan mailbox limit as a clear message', function () {
    Http::fake(function ($request) {
        if (str_contains($request->url(), '/domains')) {
            return Http::response(['data' => [['id' => 'dom-own', 'domain_name' => 'example.com']]], 200);
        }

        return Http::response(['status' => 'error', 'details' => 'mailbox limit reached'], 403);
    });
    [$user, $service] = emailService(panelServerE());

    $result = (new PanelicaModule)->createEmail($service, 'dom-own', 'info', 'password123');

    expect($result['success'])->toBeFalse()
        ->and(strtolower($result['message']))->toContain('limit');
});

// ---------------------------------------------------------------------------
// Controller: client-ownership gate
// ---------------------------------------------------------------------------

it('shows the email tab to the service owner', function () {
    fakePanelEmailApi([
        ['id' => 'm1', 'email' => 'info@example.com', 'domain_id' => 'dom-own', 'quota_mb' => 1024, 'used_quota_mb' => 0, 'status' => 'active'],
    ]);
    [$user, $service] = emailService(panelServerE());

    $this->actingAs($user)
        ->get(route('client.services.emails', $service))
        ->assertOk()
        ->assertSee('info@example.com');
});

it('forbids the email tab for another client\'s service', function () {
    fakePanelEmailApi([]);
    [$owner, $service] = emailService(panelServerE());
    $intruder = User::factory()->create();
    $intruder->clients()->attach(Client::factory()->create()->id);

    $this->actingAs($intruder)
        ->get(route('client.services.emails', $service))
        ->assertForbidden();
});

it('forbids creating a mailbox on another client\'s service', function () {
    fakePanelEmailApi([]);
    [$owner, $service] = emailService(panelServerE());
    $intruder = User::factory()->create();
    $intruder->clients()->attach(Client::factory()->create()->id);

    $this->actingAs($intruder)
        ->post(route('client.services.emails.store', $service), [
            'domain_id' => 'dom-own', 'local_part' => 'x', 'password' => 'password123',
        ])
        ->assertForbidden();

    Http::assertNotSent(fn ($r) => $r->method() === 'POST' && str_contains($r->url(), '/v1/email-accounts'));
});
