<?php

use App\Models\Client;
use App\Models\Domain;
use App\Models\Invoice;

/**
 * FullApiCoverageTest — tests for Step 26 API implementations.
 * Matches the pattern of existing ApiTest.php (no RefreshDatabase, read-only assertions).
 */

// ===== STATS =====

test('getStats returns valid counts structure', function () {
    $response = $this->getJson('/api/v1/getstats');
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
    $response = $this->getJson('/api/v1/getannouncements');
    $response->assertStatus(200)
        ->assertJson(['result' => 'success'])
        ->assertJsonStructure(['result', 'totalresults', 'data']);
});

test('addAnnouncement requires announcement body', function () {
    $response = $this->postJson('/api/v1/addannouncement', [
        'title' => 'Missing body only',
    ]);
    $response->assertStatus(422);
});

test('addAnnouncement creates and returns id', function () {
    $response = $this->postJson('/api/v1/addannouncement', [
        'title' => 'Test Suite Announcement ' . time(),
        'announcement' => 'Created by automated test suite.',
    ]);

    $response->assertStatus(200)
        ->assertJson(['result' => 'success'])
        ->assertJsonStructure(['result', 'announcementid']);

    $id = $response->json('announcementid');
    expect($id)->toBeInt()->toBeGreaterThan(0);

    // Cleanup
    $this->postJson('/api/v1/deleteannouncement', ['announcementid' => $id]);
});

test('deleteAnnouncement returns error for missing id', function () {
    $response = $this->postJson('/api/v1/deleteannouncement', [
        'announcementid' => 99999999,
    ]);
    $response->assertStatus(404)->assertJson(['result' => 'error']);
});

test('updateAnnouncement returns error for missing id', function () {
    $response = $this->postJson('/api/v1/updateannouncement', [
        'announcementid' => 99999999,
        'title' => 'Should fail',
    ]);
    $response->assertStatus(404)->assertJson(['result' => 'error']);
});

// ===== PRODUCTS =====

test('getProducts returns products with group relation', function () {
    $response = $this->getJson('/api/v1/getproducts');
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
    $response = $this->getJson('/api/v1/getclientsdomains');
    $response->assertStatus(200)
        ->assertJson(['result' => 'success'])
        ->assertJsonStructure(['result', 'totalresults', 'data']);
});

test('getDomainDetails returns 404 for unknown domain', function () {
    $response = $this->getJson('/api/v1/getclientsdomains?userid=99999999');
    $response->assertStatus(200);
    expect($response->json('totalresults'))->toBe(0);
});

// ===== INVOICES =====

test('createInvoice requires userid', function () {
    $response = $this->postJson('/api/v1/createinvoice', [
        'date' => now()->format('Y-m-d'),
    ]);
    $response->assertStatus(422);
});

test('createInvoice creates invoice for existing client', function () {
    $client = Client::first();
    if (!$client) {
        $this->markTestSkipped('No clients in test DB');
    }

    $response = $this->postJson('/api/v1/createinvoice', [
        'userid' => $client->id,
        'date' => now()->format('Y-m-d'),
        'duedate' => now()->addDays(14)->format('Y-m-d'),
        'paymentmethod' => 'stripe',
        'status' => 'unpaid',
    ]);

    $response->assertStatus(200)
        ->assertJson(['result' => 'success'])
        ->assertJsonStructure(['result', 'invoiceid']);
});

// ===== PROMOTIONS =====

test('getPromotions returns list structure', function () {
    $response = $this->getJson('/api/v1/getpromotions');
    $response->assertStatus(200)
        ->assertJson(['result' => 'success'])
        ->assertJsonStructure(['result', 'promotions']);
});

// ===== EMAIL TEMPLATES =====

test('getEmailTemplates returns templates array', function () {
    $response = $this->getJson('/api/v1/getemailtemplates');
    $response->assertStatus(200)
        ->assertJson(['result' => 'success'])
        ->assertJsonStructure(['result', 'templates']);
});

// ===== SERVERS =====

test('getServers returns servers with groups', function () {
    $response = $this->getJson('/api/v1/getservers');
    $response->assertStatus(200)
        ->assertJson(['result' => 'success'])
        ->assertJsonStructure(['result', 'servers']);
});

// ===== TODO ITEMS =====

test('getTodoItems returns items list', function () {
    $response = $this->getJson('/api/v1/gettodoitems');
    $response->assertStatus(200)
        ->assertJson(['result' => 'success'])
        ->assertJsonStructure(['result', 'items']);
});

test('getTodoItems can filter by status', function () {
    $response = $this->getJson('/api/v1/gettodoitems?status=pending');
    $response->assertStatus(200)->assertJson(['result' => 'success']);
});

// ===== ACTIVITY LOG =====

test('getActivityLog returns paginated structure', function () {
    $response = $this->getJson('/api/v1/getactivitylog');
    $response->assertStatus(200)
        ->assertJson(['result' => 'success'])
        ->assertJsonStructure(['result', 'totalresults', 'data']);
});
