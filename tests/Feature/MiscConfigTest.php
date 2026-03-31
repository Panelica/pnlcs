<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\BannedIp;
use App\Models\TodoItem;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

// ============================================================
// Helpers
// ============================================================

function makeMiscAdmin(): Admin
{
    $role = AdminRole::factory()->fullAdmin()->create();
    return Admin::factory()->create(['role_id' => $role->id]);
}

// ============================================================
// Banned IPs
// ============================================================

test('admin can view banned IPs page', function () {
    $admin = makeMiscAdmin();
    $this->actingAs($admin, 'admin')
         ->get(route('admin.config.banned-ips'))
         ->assertStatus(200);
});

test('admin can ban an IP address', function () {
    $admin = makeMiscAdmin();
    $this->actingAs($admin, 'admin')
         ->post(route('admin.config.banned-ips.store'), [
             'ip'     => '192.168.1.100',
             'reason' => 'Brute force attempt',
         ])
         ->assertRedirect();
    $this->assertDatabaseHas('banned_ips', ['ip' => '192.168.1.100']);
});

test('admin can remove a banned IP', function () {
    $admin     = makeMiscAdmin();
    $bannedIp  = BannedIp::factory()->create(['ip' => '10.0.0.1']);
    $this->actingAs($admin, 'admin')
         ->delete(route('admin.config.banned-ips.destroy', $bannedIp))
         ->assertRedirect();
    $this->assertDatabaseMissing('banned_ips', ['id' => $bannedIp->id]);
});

test('banned IP creation validates required ip field', function () {
    $admin = makeMiscAdmin();
    $this->actingAs($admin, 'admin')
         ->post(route('admin.config.banned-ips.store'), [])
         ->assertSessionHasErrors(['ip']);
});

// ============================================================
// Banned Emails
// ============================================================

test('admin can view banned emails page', function () {
    $admin = makeMiscAdmin();
    $this->actingAs($admin, 'admin')
         ->get(route('admin.config.banned-emails'))
         ->assertStatus(200);
});

test('admin can ban an email address', function () {
    $admin = makeMiscAdmin();
    $this->actingAs($admin, 'admin')
         ->post(route('admin.config.banned-emails.store'), [
             'email'  => 'spam@example.com',
             'reason' => 'Spam',
         ])
         ->assertRedirect();
    $this->assertDatabaseHas('banned_emails', ['domain' => 'spam@example.com']);
});

// ============================================================
// To-Do
// ============================================================

test('admin can view todo page', function () {
    $admin = makeMiscAdmin();
    $this->actingAs($admin, 'admin')
         ->get(route('admin.config.todo'))
         ->assertStatus(200);
});

test('admin can create a todo item', function () {
    $admin = makeMiscAdmin();
    $this->actingAs($admin, 'admin')
         ->post(route('admin.config.todo.store'), [
             'title'  => 'Fix billing bug',
             'status' => 'New',
         ])
         ->assertRedirect();
    $this->assertDatabaseHas('todo_items', ['title' => 'Fix billing bug']);
});

test('admin can update a todo item', function () {
    $admin = makeMiscAdmin();
    $item  = TodoItem::factory()->create(['title' => 'Old task', 'status' => 'New']);
    $this->actingAs($admin, 'admin')
         ->put(route('admin.config.todo.update', $item), [
             'title'  => 'Updated task',
             'status' => 'Completed',
         ])
         ->assertRedirect();
    $this->assertDatabaseHas('todo_items', ['title' => 'Updated task', 'status' => 'Completed']);
});

test('admin can delete a todo item', function () {
    $admin = makeMiscAdmin();
    $item  = TodoItem::factory()->create();
    $this->actingAs($admin, 'admin')
         ->delete(route('admin.config.todo.destroy', $item))
         ->assertRedirect();
    $this->assertDatabaseMissing('todo_items', ['id' => $item->id]);
});

// ============================================================
// Activity Log
// ============================================================

test('admin can view activity log page', function () {
    $admin = makeMiscAdmin();
    $this->actingAs($admin, 'admin')
         ->get(route('admin.config.activity-log'))
         ->assertStatus(200);
});

test('activity log is read-only (no store route)', function () {
    expect(Route::has('admin.config.activity-log.store'))->toBeFalse();
});

// ============================================================
// Affiliates, Quotes, Transactions (read-only views)
// ============================================================

test('admin can view affiliates page', function () {
    $admin = makeMiscAdmin();
    $this->actingAs($admin, 'admin')
         ->get(route('admin.config.affiliates'))
         ->assertStatus(200);
});

test('admin can view quotes page', function () {
    $admin = makeMiscAdmin();
    $this->actingAs($admin, 'admin')
         ->get(route('admin.config.quotes'))
         ->assertStatus(200);
});

test('admin can view transactions page', function () {
    $admin = makeMiscAdmin();
    $this->actingAs($admin, 'admin')
         ->get(route('admin.config.transactions'))
         ->assertStatus(200);
});

// ============================================================
// System pages
// ============================================================

test('admin can view system database page', function () {
    $admin = makeMiscAdmin();
    $this->actingAs($admin, 'admin')
         ->get(route('admin.config.system-database'))
         ->assertStatus(200);
});

test('admin can view system phpinfo page', function () {
    $admin = makeMiscAdmin();
    $this->actingAs($admin, 'admin')
         ->get(route('admin.config.system-phpinfo'))
         ->assertStatus(200);
});

// ============================================================
// Unauthenticated access is blocked
// ============================================================

test('unauthenticated user cannot view banned IPs', function () {
    $this->get(route('admin.config.banned-ips'))
         ->assertRedirect(route('admin.login'));
});

test('unauthenticated user cannot view activity log', function () {
    $this->get(route('admin.config.activity-log'))
         ->assertRedirect(route('admin.login'));
});

test('unauthenticated user cannot view todo list', function () {
    $this->get(route('admin.config.todo'))
         ->assertRedirect(route('admin.login'));
});
