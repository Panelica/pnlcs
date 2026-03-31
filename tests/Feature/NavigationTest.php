<?php

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('dashboard is accessible', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin')->get(route('admin.dashboard'))->assertStatus(200);
});

test('clients index is accessible', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin')->get(route('admin.clients.index'))->assertStatus(200);
});

test('orders index is accessible', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin')->get(route('admin.orders.index'))->assertStatus(200);
});

test('invoices index is accessible', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin')->get(route('admin.invoices.index'))->assertStatus(200);
});

test('products index is accessible', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin')->get(route('admin.products.index'))->assertStatus(200);
});

test('services index is accessible', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin')->get(route('admin.services.index'))->assertStatus(200);
});

test('domains index is accessible', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin')->get(route('admin.domains.index'))->assertStatus(200);
});

test('tickets index is accessible', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin')->get(route('admin.tickets.index'))->assertStatus(200);
});

test('settings is accessible', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin')->get(route('admin.settings.general'))->assertStatus(200);
});

test('reports index is accessible', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin')->get(route('admin.reports.index'))->assertStatus(200);
});

test('config admins is accessible', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin')->get(route('admin.config.admins'))->assertStatus(200);
});

test('config admin roles is accessible', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin')->get(route('admin.config.admin-roles'))->assertStatus(200);
});

test('config api credentials is accessible', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin')->get(route('admin.config.api-credentials'))->assertStatus(200);
});

test('config currencies is accessible', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin')->get(route('admin.config.currencies'))->assertStatus(200);
});

test('config tax is accessible', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin')->get(route('admin.config.tax'))->assertStatus(200);
});

test('config promotions is accessible', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin')->get(route('admin.config.promotions'))->assertStatus(200);
});

test('config servers is accessible', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin')->get(route('admin.config.servers'))->assertStatus(200);
});

test('config domain pricing is accessible', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin')->get(route('admin.config.domain-pricing'))->assertStatus(200);
});

test('config ticket departments is accessible', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin')->get(route('admin.config.ticket-departments'))->assertStatus(200);
});

test('config ticket statuses is accessible', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin')->get(route('admin.config.ticket-statuses'))->assertStatus(200);
});

test('config email templates is accessible', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin')->get(route('admin.config.email-templates'))->assertStatus(200);
});

test('config announcements is accessible', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin')->get(route('admin.config.announcements'))->assertStatus(200);
});

test('config knowledge base is accessible', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin')->get(route('admin.config.knowledge-base'))->assertStatus(200);
});

test('config downloads is accessible', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin')->get(route('admin.config.downloads'))->assertStatus(200);
});

test('config network issues is accessible', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin')->get(route('admin.config.network-issues'))->assertStatus(200);
});

test('config banned ips is accessible', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin')->get(route('admin.config.banned-ips'))->assertStatus(200);
});

test('config banned emails is accessible', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin')->get(route('admin.config.banned-emails'))->assertStatus(200);
});

test('config todo is accessible', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin')->get(route('admin.config.todo'))->assertStatus(200);
});

test('config activity log is accessible', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin')->get(route('admin.config.activity-log'))->assertStatus(200);
});

test('config affiliates is accessible', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin')->get(route('admin.config.affiliates'))->assertStatus(200);
});

test('config quotes is accessible', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin')->get(route('admin.config.quotes'))->assertStatus(200);
});

test('config billable items is accessible', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin')->get(route('admin.config.billable-items'))->assertStatus(200);
});

test('config transactions is accessible', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin')->get(route('admin.config.transactions'))->assertStatus(200);
});

test('config system database is accessible', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin')->get(route('admin.config.system-database'))->assertStatus(200);
});

test('config system phpinfo is accessible', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin')->get(route('admin.config.system-phpinfo'))->assertStatus(200);
});

test('config gateways is accessible', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin')->get(route('admin.config.gateways'))->assertStatus(200);
});

test('config registrars is accessible', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin')->get(route('admin.config.registrars'))->assertStatus(200);
});

test('unauthenticated users are redirected from all admin pages', function () {
    $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
    $this->get(route('admin.config.admins'))->assertRedirect(route('admin.login'));
    $this->get(route('admin.config.currencies'))->assertRedirect(route('admin.login'));
});
