<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectTask;

/*
 * A task is edited and deleted under its project's URL, and the task was
 * never checked to belong to that project: any task could be changed or
 * removed through any project's address.
 */
function projectWithTask(): array
{
    $project = Project::create(['client_id' => Client::factory()->create()->id, 'title' => 'P '.uniqid(), 'status' => 'pending']);
    $task = $project->tasks()->create(['task' => 'Do the thing', 'completed' => false, 'sort_order' => 1]);

    return [$project, $task];
}

test('a task cannot be edited or deleted through another project', function () {
    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);
    [$mine, $task] = projectWithTask();
    [$other] = projectWithTask();

    $this->actingAs($admin, 'admin')->put(route('admin.projects.tasks.update', [$other, $task]), ['task' => 'Hijacked'])->assertNotFound();
    $this->actingAs($admin, 'admin')->delete(route('admin.projects.tasks.destroy', [$other, $task]))->assertNotFound();

    expect($task->fresh()->task)->toBe('Do the thing');

    $this->actingAs($admin, 'admin')->put(route('admin.projects.tasks.update', [$mine, $task]), ['task' => 'Renamed'])->assertRedirect();
    expect($task->fresh()->task)->toBe('Renamed');
});
