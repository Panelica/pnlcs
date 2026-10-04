<?php

use App\Models\Admin;
use App\Models\AdminRole;

/*
 * The admin menu's "New ticket" and "Open ticket" went to the ticket list,
 * though staff can now open a ticket (#130); and nothing in the menu led back
 * to the dashboard - only the logo did.
 */

test('the menu leads to the dashboard and to opening a ticket', function () {
    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->create(['name' => 'R'.uniqid(), 'permissions' => ['view_dashboard', 'list_tickets', 'reply_tickets']])->id]);

    $html = $this->actingAs($admin, 'admin')->get(route('admin.tickets.index'))->assertOk()->getContent();
    $nav = substr($html, (int) strpos($html, 'navbar-collapse'), 20000);

    expect(substr_count($nav, 'href="'.route('admin.tickets.create').'"'))->toBe(2)
        ->and($nav)->toContain('href="'.route('admin.dashboard').'"><i class="fas fa-home"></i> '.e(__('admin.sidebar.dashboard')));
});
