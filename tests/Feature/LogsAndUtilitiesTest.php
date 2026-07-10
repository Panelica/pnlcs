<?php

use App\Models\Admin;
use App\Models\AdminRole;

/**
 * LogsAndUtilitiesTest — Step 29 features.
 * Tests do not use RefreshDatabase — they rely on the DB being pre-populated.
 */

beforeEach(function () {
    $role = AdminRole::first();
    if (!$role) {
        $role = AdminRole::factory()->fullAdmin()->create();
    }
    $admin = Admin::first();
    if (!$admin) {
        $admin = Admin::factory()->create(['role_id' => $role->id]);
    }
    $this->admin = $admin;
    $this->actingAs($this->admin, 'admin');
});

// ===== LOG VIEWS =====

test('admin can view logs index (activity log)', function () {
    $response = $this->get(route('admin.logs.index'));
    $response->assertStatus(200);
    $response->assertSee('System Logs');
});

test('admin can view gateway logs', function () {
    $response = $this->get(route('admin.logs.gateway'));
    $response->assertStatus(200);
    $response->assertSee('Gateway');
});

test('admin can view module logs', function () {
    $response = $this->get(route('admin.logs.module'));
    $response->assertStatus(200);
    $response->assertSee('Module');
});

test('admin can view email logs', function () {
    $response = $this->get(route('admin.logs.email'));
    $response->assertStatus(200);
    $response->assertSee('Email');
});

test('activity log filter by user renders without error', function () {
    $response = $this->get(route('admin.logs.index', ['user' => 'testadmin']));
    $response->assertStatus(200);
});

test('activity log filter by search renders without error', function () {
    $response = $this->get(route('admin.logs.index', ['search' => 'test']));
    $response->assertStatus(200);
});

test('gateway log filter renders without error', function () {
    $response = $this->get(route('admin.logs.gateway', ['gateway' => 'stripe']));
    $response->assertStatus(200);
});

test('email log filter by status renders without error', function () {
    $response = $this->get(route('admin.logs.email', ['status' => 'failed']));
    $response->assertStatus(200);
});

// ===== CSV EXPORTS =====

test('admin can access client CSV export endpoint', function () {
    $response = $this->get(route('admin.clients.export'));
    $response->assertStatus(200);
});

test('client CSV export returns text/csv content type', function () {
    $response = $this->get(route('admin.clients.export'));
    $response->assertStatus(200);
    $this->assertStringStartsWith('text/csv', $response->headers->get('content-type'));
});

test('client CSV export with status filter', function () {
    $response = $this->get(route('admin.clients.export', ['status' => 'active']));
    $response->assertStatus(200);
});

test('admin can access invoice CSV export endpoint', function () {
    $response = $this->get(route('admin.invoices.export'));
    $response->assertStatus(200);
});

test('invoice CSV export returns text/csv content type', function () {
    $response = $this->get(route('admin.invoices.export'));
    $response->assertStatus(200);
    $this->assertStringStartsWith('text/csv', $response->headers->get('content-type'));
});

test('invoice CSV export with status filter', function () {
    $response = $this->get(route('admin.invoices.export', ['status' => 'paid']));
    $response->assertStatus(200);
});
