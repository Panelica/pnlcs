<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Constants\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('full admin can access all pages', function () {
    $role = AdminRole::factory()->fullAdmin()->create();
    $admin = Admin::factory()->create(['role_id' => $role->id]);
    $this->actingAs($admin, 'admin')->get(route('admin.config.admins'))->assertStatus(200);
});

test('permission constants are consistent', function () {
    $all = Permissions::all();
    expect(count($all))->toBeGreaterThan(30);
    $grouped = Permissions::grouped();
    $totalFromGroups = 0;
    foreach ($grouped as $perms) { $totalFromGroups += count($perms); }
    expect($totalFromGroups)->toBe(count($all));
});

test('permission seeder creates default roles', function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    expect(AdminRole::where('name', 'Full Administrator')->exists())->toBeTrue();
    expect(AdminRole::where('name', 'Support Agent')->exists())->toBeTrue();
    expect(AdminRole::where('name', 'Billing Agent')->exists())->toBeTrue();
});

test('support agent role has limited permissions', function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    $role = AdminRole::where('name', 'Support Agent')->first();
    expect($role->permissions)->toContain(Permissions::LIST_TICKETS);
    expect($role->permissions)->not->toContain(Permissions::MANAGE_STAFF);
});

test('full admin role has all permissions', function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\PermissionSeeder']);
    $role = AdminRole::where('name', 'Full Administrator')->first();
    expect($role->is_full_admin)->toBeTrue();
    expect(count($role->permissions))->toBe(count(Permissions::all()));
});
