<?php

use App\Models\ApiCredential;
use App\Models\Client;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use Database\Factories\ApiCredentialFactory;

beforeEach(function () {
    $this->apiCred = ApiCredential::factory()->create();
    $this->apiHeaders = ['X-API-Key' => $this->apiCred->identifier, 'X-API-Secret' => ApiCredentialFactory::PLAINTEXT_SECRET];
});

test('api health returns ok', function () {
    $response = $this->getJson('/api/health');
    $response->assertStatus(200)->assertJson(['result' => 'success']);
});

test('api getstats returns statistics', function () {
    $response = $this->getJson('/api/v1/getstats', $this->apiHeaders);
    $response->assertStatus(200)
        ->assertJsonStructure(['result', 'stats' => ['total_clients', 'active_clients', 'total_invoices']]);
});

test('api getproducts returns products array', function () {
    $response = $this->getJson('/api/v1/getproducts', $this->apiHeaders);
    $response->assertStatus(200)->assertJsonStructure(['result', 'products']);
});

test('api addclient creates client', function () {
    $response = $this->postJson('/api/v1/addclient', [
        'firstname' => 'Test',
        'lastname' => 'User',
        'email' => 'testapi@example.com',
    ], $this->apiHeaders);
    $response->assertStatus(200)->assertJson(['result' => 'success']);
    expect(Client::where('email', 'testapi@example.com')->exists())->toBeTrue();
});

test('api getclients returns paginated list', function () {
    Client::factory()->count(3)->create();
    $response = $this->getJson('/api/v1/getclients', $this->apiHeaders);
    $response->assertStatus(200)
        ->assertJsonStructure(['result', 'totalresults', 'startnumber', 'numreturned', 'data']);
});

test('api getclientsdetails returns client', function () {
    $client = Client::factory()->create();
    $response = $this->getJson("/api/v1/getclientsdetails?clientid={$client->id}", $this->apiHeaders);
    $response->assertStatus(200)->assertJsonPath('result', 'success');
});

test('api getclientsdetails returns error for missing client', function () {
    $response = $this->getJson('/api/v1/getclientsdetails?clientid=99999', $this->apiHeaders);
    $response->assertStatus(404)->assertJson(['result' => 'error']);
});

test('api pnlcsdetails returns version', function () {
    $response = $this->getJson('/api/v1/pnlcsdetails', $this->apiHeaders);
    $response->assertStatus(200)->assertJsonStructure(['result', 'pnlcs' => ['version']]);
});

test('api reports the same company name as every screen', function () {
    // The API used to read the raw CompanyName setting while the panel, the
    // invoices and the emails all go through company_name(), which prefers the
    // white-label override - so integrations were told a different name than
    // customers ever saw whenever the override was set.
    \App\Models\Setting::set('CompanyName', 'Registered Name Ltd', 'general');
    \App\Models\Setting::set('whitelabel_company_name', 'Customer-Facing Brand', 'whitelabel');
    app()->forgetInstance('pnlcs.company_name');

    $response = $this->getJson('/api/v1/pnlcsdetails', $this->apiHeaders);

    $response->assertStatus(200)
        ->assertJsonPath('pnlcs.company_name', 'Customer-Facing Brand');
});

test('api openticket creates ticket', function () {
    // This used to post against department 1 and assert nothing at all when it
    // was missing, so it passed whatever the endpoint did.
    $department = TicketDepartment::factory()->create();

    $response = $this->postJson('/api/v1/openticket', [
        'deptid' => $department->id,
        'subject' => 'Test Ticket',
        'message' => 'This is a test ticket',
        'email' => 'test@example.com',
    ], $this->apiHeaders);

    $response->assertStatus(200)->assertJson(['result' => 'success']);

    $ticket = Ticket::where('tid', $response->json('tid'))->firstOrFail();
    expect($ticket->department_id)->toBe($department->id)
        ->and($ticket->title)->toBe('Test Ticket')
        // 'Open' is what every other door writes, TicketService included; the
        // api's lower-case 'open' was the odd one out and only survived
        // because MySQL compares these two the same.
        ->and($ticket->status)->toBe('Open');
});

test('api gettldpricing returns pricing', function () {
    $response = $this->getJson('/api/v1/gettldpricing', $this->apiHeaders);
    $response->assertStatus(200)->assertJsonStructure(['result', 'pricing']);
});
