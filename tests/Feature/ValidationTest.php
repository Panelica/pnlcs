<?php

use App\Models\Admin;
use App\Models\Client;
use App\Models\User;


test('store client requires first_name', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin')
        ->post(route('admin.clients.store'), ['email' => 'test@test.com'])
        ->assertSessionHasErrors('first_name');
});

test('store client requires valid email', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin')
        ->post(route('admin.clients.store'), [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'not-an-email',
            'status' => 'active',
        ])
        ->assertSessionHasErrors('email');
});

test('store client rejects duplicate email', function () {
    $admin = Admin::factory()->create();
    Client::factory()->create(['email' => 'existing@test.com']);
    $this->actingAs($admin, 'admin')
        ->post(route('admin.clients.store'), [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'existing@test.com',
            'status' => 'active',
        ])
        ->assertSessionHasErrors('email');
});

test('store client rejects invalid status', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin')
        ->post(route('admin.clients.store'), [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'test@test.com',
            'status' => 'invalid_status',
        ])
        ->assertSessionHasErrors('status');
});

test('XSS in client name is escaped in output', function () {
    $admin = Admin::factory()->create();
    $client = Client::factory()->create([
        'first_name' => '<script>alert("xss")</script>',
        'last_name' => 'Test',
    ]);
    $response = $this->actingAs($admin, 'admin')
        ->get(route('admin.clients.show', $client));
    $response->assertDontSee('<script>alert("xss")</script>', false);
});

test('store admin requires all fields', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin')
        ->post(route('admin.config.admins.store'), [])
        ->assertSessionHasErrors(['username', 'email', 'password', 'first_name', 'last_name', 'role_id']);
});

test('currency code must be 3 characters', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin')
        ->post(route('admin.config.currencies.store'), [
            'code' => 'TOOLONG',
            'rate' => 1.0,
        ])
        ->assertSessionHasErrors('code');
});

test('tax rate must be numeric', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin')
        ->post(route('admin.config.tax.store'), [
            'name' => 'VAT',
            'tax_rate' => 'not-a-number',
        ])
        ->assertSessionHasErrors('tax_rate');
});

test('promotion code must be unique', function () {
    $admin = Admin::factory()->create();
    \App\Models\Promotion::factory()->create(['code' => 'TESTCODE']);
    $this->actingAs($admin, 'admin')
        ->post(route('admin.config.promotions.store'), [
            'code' => 'TESTCODE',
            'type' => 'Percentage',
            'value' => 10,
        ])
        ->assertSessionHasErrors('code');
});
