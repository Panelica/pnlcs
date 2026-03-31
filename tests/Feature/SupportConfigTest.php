<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\EmailTemplate;
use App\Models\TicketDepartment;
use App\Models\TicketStatus;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function makeSupportAdmin(): Admin
{
    $role = AdminRole::factory()->fullAdmin()->create();
    return Admin::factory()->create(['role_id' => $role->id]);
}

// ─── Ticket Departments ───────────────────────────────────────────────────────

test('admin can view ticket departments', function () {
    $admin = makeSupportAdmin();
    $this->actingAs($admin, 'admin')
        ->get(route('admin.config.ticket-departments'))
        ->assertStatus(200)
        ->assertSee('Ticket Departments');
});

test('admin can create department', function () {
    $admin = makeSupportAdmin();
    $this->actingAs($admin, 'admin')
        ->post(route('admin.config.ticket-departments.store'), [
            'name'             => 'Billing Support',
            'description'      => 'Handle billing queries',
            'email'            => 'billing@example.com',
            'clients_only'     => 1,
            'hidden'           => 0,
            'sort_order'       => 1,
            'feedback_request' => 0,
        ])
        ->assertRedirect();
    $this->assertDatabaseHas('ticket_departments', ['name' => 'Billing Support', 'email' => 'billing@example.com']);
});

test('admin can update department', function () {
    $admin = makeSupportAdmin();
    $dept  = TicketDepartment::factory()->create();
    $this->actingAs($admin, 'admin')
        ->put(route('admin.config.ticket-departments.update', $dept), [
            'name'       => 'Updated Department',
            'sort_order' => 5,
        ])
        ->assertRedirect();
    $this->assertDatabaseHas('ticket_departments', ['id' => $dept->id, 'name' => 'Updated Department']);
});

test('admin can delete department', function () {
    $admin = makeSupportAdmin();
    $dept  = TicketDepartment::factory()->create();
    $this->actingAs($admin, 'admin')
        ->delete(route('admin.config.ticket-departments.destroy', $dept))
        ->assertRedirect();
    $this->assertDatabaseMissing('ticket_departments', ['id' => $dept->id]);
});

// ─── Ticket Statuses ──────────────────────────────────────────────────────────

test('admin can view ticket statuses', function () {
    $admin = makeSupportAdmin();
    $this->actingAs($admin, 'admin')
        ->get(route('admin.config.ticket-statuses'))
        ->assertStatus(200)
        ->assertSee('Ticket Statuses');
});

test('admin can create ticket status', function () {
    $admin = makeSupportAdmin();
    $this->actingAs($admin, 'admin')
        ->post(route('admin.config.ticket-statuses.store'), [
            'title'         => 'On Hold',
            'color'         => '#f59e0b',
            'sort_order'    => 3,
            'show_active'   => 1,
            'show_awaiting' => 0,
            'auto_close'    => 0,
        ])
        ->assertRedirect();
    $this->assertDatabaseHas('ticket_statuses', ['title' => 'On Hold', 'color' => '#f59e0b']);
});

test('admin can update ticket status', function () {
    $admin  = makeSupportAdmin();
    $status = TicketStatus::factory()->create();
    $this->actingAs($admin, 'admin')
        ->put(route('admin.config.ticket-statuses.update', $status), [
            'title'      => 'Updated Status',
            'color'      => '#ef4444',
            'sort_order' => 0,
        ])
        ->assertRedirect();
    $this->assertDatabaseHas('ticket_statuses', ['id' => $status->id, 'title' => 'Updated Status']);
});

test('admin can delete ticket status', function () {
    $admin  = makeSupportAdmin();
    $status = TicketStatus::factory()->create();
    $this->actingAs($admin, 'admin')
        ->delete(route('admin.config.ticket-statuses.destroy', $status))
        ->assertRedirect();
    $this->assertDatabaseMissing('ticket_statuses', ['id' => $status->id]);
});

// ─── Email Templates ─────────────────────────────────────────────────────────

test('admin can view email templates', function () {
    $admin = makeSupportAdmin();
    $this->actingAs($admin, 'admin')
        ->get(route('admin.config.email-templates'))
        ->assertStatus(200)
        ->assertSee('Email Templates');
});

test('admin can update email template', function () {
    $admin    = makeSupportAdmin();
    $template = EmailTemplate::factory()->create(['type' => 'general', 'name' => 'Welcome Email']);
    $this->actingAs($admin, 'admin')
        ->put(route('admin.config.email-templates.update', $template), [
            'name'       => 'Welcome Email',
            'subject'    => 'Welcome to our service!',
            'message'    => '<p>Hello {client_name}, welcome aboard!</p>',
            'disabled'   => 0,
        ])
        ->assertRedirect();
    $this->assertDatabaseHas('email_templates', [
        'id'      => $template->id,
        'subject' => 'Welcome to our service!',
    ]);
});

test('admin can disable email template', function () {
    $admin    = makeSupportAdmin();
    $template = EmailTemplate::factory()->create(['disabled' => false]);
    $this->actingAs($admin, 'admin')
        ->put(route('admin.config.email-templates.update', $template), [
            'name'     => $template->name,
            'subject'  => $template->subject,
            'message'  => $template->message,
            'disabled' => 1,
        ])
        ->assertRedirect();
    $this->assertDatabaseHas('email_templates', ['id' => $template->id, 'disabled' => true]);
});

test('guest cannot access ticket departments', function () {
    $this->get(route('admin.config.ticket-departments'))
        ->assertRedirect(route('admin.login'));
});

test('guest cannot access email templates', function () {
    $this->get(route('admin.config.email-templates'))
        ->assertRedirect(route('admin.login'));
});
