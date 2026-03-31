<?php

use App\Models\Admin;
use App\Models\AdminRole;


test('admin login page loads', function () {
    $response = $this->get(route('admin.login'));
    $response->assertStatus(200)->assertSee('PNLCS');
});

test('admin can login with valid credentials', function () {
    $role = AdminRole::factory()->fullAdmin()->create();
    $admin = Admin::factory()->create([
        'role_id' => $role->id,
        'username' => 'testadmin',
        'password' => 'secret123',
    ]);

    $response = $this->post(route('admin.login.submit'), [
        'username' => 'testadmin',
        'password' => 'secret123',
    ]);

    $response->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticatedAs($admin, 'admin');
});

test('admin cannot login with invalid credentials', function () {
    $role = AdminRole::factory()->fullAdmin()->create();
    Admin::factory()->create([
        'role_id' => $role->id,
        'username' => 'testadmin',
        'password' => 'secret123',
    ]);

    $response = $this->post(route('admin.login.submit'), [
        'username' => 'testadmin',
        'password' => 'wrongpassword',
    ]);

    $response->assertSessionHasErrors('username');
    $this->assertGuest('admin');
});

test('disabled admin cannot login', function () {
    $role = AdminRole::factory()->fullAdmin()->create();
    Admin::factory()->create([
        'role_id' => $role->id,
        'username' => 'disabled',
        'password' => 'secret123',
        'is_disabled' => true,
    ]);

    $response = $this->post(route('admin.login.submit'), [
        'username' => 'disabled',
        'password' => 'secret123',
    ]);

    $response->assertSessionHasErrors('username');
    $this->assertGuest('admin');
});

test('unauthenticated admin is redirected to login', function () {
    $response = $this->get(route('admin.dashboard'));
    $response->assertRedirect(route('admin.login'));
});

test('authenticated admin can access dashboard', function () {
    $role = AdminRole::factory()->fullAdmin()->create();
    $admin = Admin::factory()->create(['role_id' => $role->id]);

    $response = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'));
    $response->assertStatus(200)->assertSee('Welcome to PNLCS');
});

test('admin can logout', function () {
    $role = AdminRole::factory()->fullAdmin()->create();
    $admin = Admin::factory()->create(['role_id' => $role->id]);

    $this->actingAs($admin, 'admin')->post(route('admin.logout'));
    $this->assertGuest('admin');
});

test('admin last login is updated', function () {
    $role = AdminRole::factory()->fullAdmin()->create();
    $admin = Admin::factory()->create([
        'role_id' => $role->id,
        'username' => 'logintrack',
        'password' => 'secret123',
        'last_login' => null,
    ]);

    $this->post(route('admin.login.submit'), [
        'username' => 'logintrack',
        'password' => 'secret123',
    ]);

    $admin->refresh();
    expect($admin->last_login)->not->toBeNull();
});

test('permission middleware blocks unauthorized', function () {
    $role = AdminRole::factory()->create(['permissions' => ['list_clients']]);
    $admin = Admin::factory()->create(['role_id' => $role->id]);

    Route::middleware(['web', 'admin.auth', 'admin.permission:delete_clients'])
        ->get('/admin/test-perm', fn () => 'OK');

    $response = $this->actingAs($admin, 'admin')->get('/admin/test-perm');
    $response->assertStatus(403);
});

test('full admin bypasses permissions', function () {
    $role = AdminRole::factory()->fullAdmin()->create();
    $admin = Admin::factory()->create(['role_id' => $role->id]);

    Route::middleware(['web', 'admin.auth', 'admin.permission:anything'])
        ->get('/admin/test-bypass', fn () => 'OK');

    $response = $this->actingAs($admin, 'admin')->get('/admin/test-bypass');
    $response->assertStatus(200);
});
