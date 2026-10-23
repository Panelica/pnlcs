<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Ticket;
use App\Models\TicketDepartment;

/**
 * The departments list has a Tickets column that printed tickets_count, which
 * the controller never loaded: every department showed 0.
 */
it('counts each department\'s tickets', function () {
    $sales = TicketDepartment::factory()->create(['name' => 'Count Sales', 'sort_order' => 1]);
    $empty = TicketDepartment::factory()->create(['name' => 'Count Empty', 'sort_order' => 2]);
    Ticket::factory()->count(3)->create(['department_id' => $sales->id]);

    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);
    $departments = $this->actingAs($admin, 'admin')
        ->get(route('admin.config.ticket-departments'))->assertOk()
        ->viewData('departments');

    expect($departments->firstWhere('id', $sales->id)->tickets_count)->toBe(3)
        ->and($departments->firstWhere('id', $empty->id)->tickets_count)->toBe(0);
});
