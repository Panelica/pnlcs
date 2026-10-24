<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Project;
use Modules\Addons\ProjectManagement\ProjectManagementModule;

/**
 * projects.admin_id is the staff member a project belongs to, and the Project
 * Management addon lists it as "Assigned". Creating a project never set it, so
 * that column was empty for every project.
 */
it('records the staff member who created the project', function () {
    $admin = Admin::factory()->create([
        'first_name' => 'Owner', 'last_name' => 'Staff',
        'role_id' => AdminRole::factory()->fullAdmin()->create()->id,
    ]);

    $this->actingAs($admin, 'admin')->post(route('admin.projects.store'), [
        'client_id' => Client::factory()->create()->id,
        'title' => 'Owned project',
        'status' => 'pending',
    ])->assertRedirect();

    $project = Project::where('title', 'Owned project')->latest('id')->first();
    expect($project->admin_id)->toBe($admin->id)
        ->and((new ProjectManagementModule)->output(request()))->toContain('Owner Staff');
});
