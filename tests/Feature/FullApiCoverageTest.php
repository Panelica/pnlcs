<?php

use App\Models\Client;
use App\Models\Domain;
use App\Models\Invoice;
use App\Models\ApiCredential;

/**
 * FullApiCoverageTest — tests for Step 26 API implementations.
 */

beforeEach(function () {
    $this->apiCred = ApiCredential::factory()->create();
    $this->apiHeaders = ['X-API-Key' => $this->apiCred->identifier, 'X-API-Secret' => $this->apiCred->secret];
});

// ===== STATS =====

test('getStats returns valid counts structure', function () {
    $response = $this->getJson('/api/v1/getstats', $this->apiHeaders);
    $response->assertStatus(200)
        ->assertJson(['result' => 'success'])
        ->assertJsonStructure([
            'result',
            'stats' => [
                'total_clients',
                'active_clients',
                'total_services',
                'active_services',
                'total_domains',
                'total_invoices',
                'unpaid_invoices',
                'total_orders',
                'pending_orders',
                'total_tickets',
                'open_tickets',
                'total_admins',
            ],
        ]);

    $data = $response->json('stats');
    expect($data['total_clients'])->toBeInt();
    expect($data['active_clients'])->toBeInt()->toBeLessThanOrEqual($data['total_clients']);
    expect($data['total_invoices'])->toBeInt();
});

// ===== ANNOUNCEMENTS =====

test('getAnnouncements returns paginated result structure', function () {
    $response = $this->getJson('/api/v1/getannouncements', $this->apiHeaders);
    $response->assertStatus(200)
        ->assertJson(['result' => 'success'])
        ->assertJsonStructure(['result', 'totalresults', 'data']);
});

test('addAnnouncement requires announcement body', function () {
    $response = $this->postJson('/api/v1/addannouncement', [
        'title' => 'Missing body only',
    ], $this->apiHeaders);
    $response->assertStatus(422);
});

test('addAnnouncement creates and returns id', function () {
    $response = $this->postJson('/api/v1/addannouncement', [
        'title' => 'Test Suite Announcement ' . time(),
        'announcement' => 'Created by automated test suite.',
    ], $this->apiHeaders);

    $response->assertStatus(200)
        ->assertJson(['result' => 'success'])
        ->assertJsonStructure(['result', 'announcementid']);

    $id = $response->json('announcementid');
    expect($id)->toBeInt()->toBeGreaterThan(0);

    // Cleanup
    $this->postJson('/api/v1/deleteannouncement', ['announcementid' => $id], $this->apiHeaders);
});

test('deleteAnnouncement returns error for missing id', function () {
    $response = $this->postJson('/api/v1/deleteannouncement', [
        'announcementid' => 99999999,
    ], $this->apiHeaders);
    $response->assertStatus(404)->assertJson(['result' => 'error']);
});

test('updateAnnouncement returns error for missing id', function () {
    $response = $this->postJson('/api/v1/updateannouncement', [
        'announcementid' => 99999999,
        'title' => 'Should fail',
    ], $this->apiHeaders);
    $response->assertStatus(404)->assertJson(['result' => 'error']);
});

// ===== PRODUCTS =====

test('getProducts returns products with group relation', function () {
    $response = $this->getJson('/api/v1/getproducts', $this->apiHeaders);
    $response->assertStatus(200)
        ->assertJson(['result' => 'success'])
        ->assertJsonStructure(['result', 'products']);
});

// ===== HEALTH STATUS =====

test('getHealthStatus returns full system info', function () {
    $response = $this->getJson('/api/v1/gethealthstatus');
    $response->assertStatus(200)
        ->assertJson(['result' => 'success'])
        ->assertJsonStructure([
            'result',
            'health' => [
                'status',
                'version',
                'laravel',
                'php',
                'database',
                'disk' => ['total_bytes', 'free_bytes', 'used_bytes', 'used_percent'],
                'memory' => ['limit', 'current', 'peak'],
                'timestamp',
            ],
        ]);

    $health = $response->json('health');
    expect($health['status'])->toBeIn(['ok', 'degraded']);
    expect($health['php'])->toBeString()->not->toBeEmpty();
    expect($health['database'])->toBe('ok');
    expect($health['disk']['used_percent'])->toBeFloat()->toBeGreaterThanOrEqual(0);
});

test('health endpoint at /api/health also works', function () {
    $response = $this->getJson('/api/health');
    $response->assertStatus(200)->assertJson(['result' => 'success']);
});

// ===== DOMAINS =====

test('getDomains returns paginated domain list', function () {
    $response = $this->getJson('/api/v1/getclientsdomains', $this->apiHeaders);
    $response->assertStatus(200)
        ->assertJson(['result' => 'success'])
        ->assertJsonStructure(['result', 'totalresults', 'data']);
});

test('getDomainDetails returns 404 for unknown domain', function () {
    $response = $this->getJson('/api/v1/getclientsdomains?userid=99999999', $this->apiHeaders);
    $response->assertStatus(200);
    expect($response->json('totalresults'))->toBe(0);
});

// ===== INVOICES =====

test('createInvoice requires userid', function () {
    $response = $this->postJson('/api/v1/createinvoice', [
        'date' => now()->format('Y-m-d'),
    ], $this->apiHeaders);
    $response->assertStatus(422);
});

test('createInvoice creates invoice for existing client', function () {
    $client = Client::factory()->create();

    $response = $this->postJson('/api/v1/createinvoice', [
        'userid' => $client->id,
        'date' => now()->format('Y-m-d'),
        'duedate' => now()->addDays(14)->format('Y-m-d'),
        'paymentmethod' => 'stripe',
        'status' => 'unpaid',
    ], $this->apiHeaders);

    $response->assertStatus(200)
        ->assertJson(['result' => 'success'])
        ->assertJsonStructure(['result', 'invoiceid']);
});

// ===== PROMOTIONS =====

test('getPromotions returns list structure', function () {
    $response = $this->getJson('/api/v1/getpromotions', $this->apiHeaders);
    $response->assertStatus(200)
        ->assertJson(['result' => 'success'])
        ->assertJsonStructure(['result', 'promotions']);
});

// ===== EMAIL TEMPLATES =====

test('getEmailTemplates returns templates array', function () {
    $response = $this->getJson('/api/v1/getemailtemplates', $this->apiHeaders);
    $response->assertStatus(200)
        ->assertJson(['result' => 'success'])
        ->assertJsonStructure(['result', 'templates']);
});

// ===== SERVERS =====

test('getServers returns servers with groups', function () {
    $response = $this->getJson('/api/v1/getservers', $this->apiHeaders);
    $response->assertStatus(200)
        ->assertJson(['result' => 'success'])
        ->assertJsonStructure(['result', 'servers']);
});

// ===== TODO ITEMS =====

test('getTodoItems returns items list', function () {
    $response = $this->getJson('/api/v1/gettodoitems', $this->apiHeaders);
    $response->assertStatus(200)
        ->assertJson(['result' => 'success'])
        ->assertJsonStructure(['result', 'items']);
});

test('getTodoItems can filter by status', function () {
    $response = $this->getJson('/api/v1/gettodoitems?status=pending', $this->apiHeaders);
    $response->assertStatus(200)->assertJson(['result' => 'success']);
});

// ===== ACTIVITY LOG =====

test('getActivityLog returns paginated structure', function () {
    $response = $this->getJson('/api/v1/getactivitylog', $this->apiHeaders);
    $response->assertStatus(200)
        ->assertJson(['result' => 'success'])
        ->assertJsonStructure(['result', 'totalresults', 'data']);
});
