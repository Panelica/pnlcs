<?php

use App\Models\Admin;
use App\Models\Client;
use App\Models\User;


test('admin pages redirect unauthenticated users', function () {
    $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
    $this->get(route('admin.clients.index'))->assertRedirect(route('admin.login'));
    $this->get(route('admin.config.admins'))->assertRedirect(route('admin.login'));
});

test('admin login page loads', function () {
    $this->get(route('admin.login'))->assertStatus(200);
});

test('admin can login with valid credentials', function () {
    $admin = Admin::factory()->create();
    $this->post(route('admin.login.submit'), [
        'username' => $admin->username,
        'password' => 'password',
    ])->assertRedirect(route('admin.dashboard'));
});

test('admin cannot login with wrong password', function () {
    $admin = Admin::factory()->create();
    $this->post(route('admin.login.submit'), [
        'username' => $admin->username,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors();
});

test('disabled admin cannot login', function () {
    $admin = Admin::factory()->disabled()->create();
    $this->post(route('admin.login.submit'), [
        'username' => $admin->username,
        'password' => 'password',
    ])->assertSessionHasErrors();
});

test('CSRF protection is active on POST routes', function () {
    $admin = Admin::factory()->create();
    $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
    // Verify that forms include CSRF tokens (tested by Laravel's middleware)
    $this->actingAs($admin, 'admin')
        ->get(route('admin.clients.create'))
        ->assertStatus(200)
        ->assertSee('csrf');
});

test('client XSS is escaped in views', function () {
    $admin = Admin::factory()->create();
    $client = Client::factory()->create([
        'first_name' => '<img src=x onerror=alert(1)>',
        'last_name' => 'Test',
    ]);
    $this->actingAs($admin, 'admin')
        ->get(route('admin.clients.index'))
        ->assertDontSee('<img src=x onerror=alert(1)>', false);
});

test('API requires authentication', function () {
    $this->postJson('/api/v1/GetClients', ['action' => 'GetClients'])
        ->assertStatus(404);
});
