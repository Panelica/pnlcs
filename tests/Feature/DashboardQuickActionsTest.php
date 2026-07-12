<?php

use App\Models\Admin;
use App\Models\AdminRole;

/**
 * The admin dashboard shows Quick Action shortcuts, each gated by the matching
 * permission. Assertions use the "+ Label" button rendering (unique to the
 * quick-actions card) so they don't collide with the nav/setup-checklist which
 * reuse the same words.
 */

it('shows quick action shortcuts on the dashboard for a full admin', function () {
    $admin = Admin::factory()->create(); // default factory role is full admin

    $this->actingAs($admin, 'admin')
        ->get('/admin')
        ->assertOk()
        ->assertSee('Quick Actions')
        ->assertSee('+ New Product')
        ->assertSee('+ Add Server')
        ->assertSee('+ Add Client')
        ->assertSee('+ New Invoice');
});

it('hides quick actions the admin lacks permission for', function () {
    $role  = AdminRole::factory()->create(['is_full_admin' => false, 'permissions' => ['create_clients']]);
    $admin = Admin::factory()->create(['role_id' => $role->id]);

    $this->actingAs($admin, 'admin')
        ->get('/admin')
        ->assertOk()
        ->assertSee('+ Add Client')        // has create_clients
        ->assertDontSee('+ New Product')   // lacks manage_products
        ->assertDontSee('+ Add Server')    // lacks manage_servers
        ->assertDontSee('+ New Invoice');  // lacks create_invoices
});
