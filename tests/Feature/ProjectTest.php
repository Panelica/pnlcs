<?php

use App\Models\Admin;
use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectMessage;


test('project can be created with factory', function () {
    $project = Project::factory()->create();
    expect($project)->toBeInstanceOf(Project::class)
        ->and($project->title)->not->toBeEmpty();
});

test('project factory states work', function () {
    $pending = Project::factory()->pending()->create();
    $inProgress = Project::factory()->inProgress()->create();
    $completed = Project::factory()->completed()->create();

    expect($pending->status)->toBe('pending')
        ->and($inProgress->status)->toBe('in_progress')
        ->and($completed->status)->toBe('completed');
});

test('project can be created via form', function () {
    $admin = Admin::factory()->create();
    $client = Client::factory()->create();
    $response = $this->actingAs($admin, 'admin')
        ->post(route('admin.projects.store'), [
            'client_id'   => $client->id,
            'title'       => 'Test Project',
            'description' => 'Test description',
            'status'      => 'pending',
            'due_date'    => now()->addDays(30)->toDateString(),
            'start_date'  => now()->toDateString(),
        ]);
    $response->assertRedirect();
    expect(Project::where('title', 'Test Project')->exists())->toBeTrue();
});

test('task can be added to project', function () {
    $admin = Admin::factory()->create();
    $project = Project::factory()->create();
    $this->actingAs($admin, 'admin')
        ->post(route('admin.projects.tasks.store', $project), [
            'task' => 'Write unit tests',
        ]);
    expect($project->tasks()->where('task', 'Write unit tests')->exists())->toBeTrue();
});

test('task status can be updated', function () {
    $admin = Admin::factory()->create();
    $project = Project::factory()->create();
    $task = $project->tasks()->create([
        'task' => 'Sample task', 'completed' => false, 'sort_order' => 0,
    ]);
    $this->actingAs($admin, 'admin')
        ->put(route('admin.projects.tasks.update', [$project, $task]), [
            'completed' => 1,
        ]);
    expect((bool)$task->fresh()->completed)->toBeTrue();
});

test('task can be deleted', function () {
    $admin = Admin::factory()->create();
    $project = Project::factory()->create();
    $task = $project->tasks()->create(['task' => 'To delete', 'completed' => false, 'sort_order' => 0]);
    $taskId = $task->id;
    $this->actingAs($admin, 'admin')
        ->delete(route('admin.projects.tasks.destroy', [$project, $task]));
    expect(ProjectTask::find($taskId))->toBeNull();
});

test('message can be added to project', function () {
    $admin = Admin::factory()->create();
    $project = Project::factory()->create();
    $this->actingAs($admin, 'admin')
        ->post(route('admin.projects.messages.store', $project), [
            'message' => 'Project is going well.',
        ]);
    expect($project->messages()->where('message', 'Project is going well.')->exists())->toBeTrue();
});

test('admin can access projects index', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin')
         ->get(route('admin.projects.index'))
         ->assertStatus(200);
});

test('admin can access projects create form', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin')
         ->get(route('admin.projects.create'))
         ->assertStatus(200);
});

test('admin can view a project', function () {
    $admin = Admin::factory()->create();
    $project = Project::factory()->create();
    $this->actingAs($admin, 'admin')
         ->get(route('admin.projects.show', $project))
         ->assertStatus(200)
         ->assertSee($project->title);
});

test('admin can edit a project', function () {
    $admin = Admin::factory()->create();
    $project = Project::factory()->create();
    $this->actingAs($admin, 'admin')
         ->get(route('admin.projects.edit', $project))
         ->assertStatus(200);
});

test('admin can update a project', function () {
    $admin = Admin::factory()->create();
    $project = Project::factory()->create(['status' => 'pending']);
    $this->actingAs($admin, 'admin')
         ->put(route('admin.projects.update', $project), [
             'title'       => 'Updated Title',
             'description' => 'Updated desc',
             'status'      => 'in_progress',
         ])
         ->assertRedirect(route('admin.projects.show', $project));
    expect($project->fresh()->status)->toBe('in_progress')
        ->and($project->fresh()->title)->toBe('Updated Title');
});

test('deleting project cascades to tasks and messages', function () {
    $admin = Admin::factory()->create();
    $project = Project::factory()->create();
    $task = $project->tasks()->create(['task' => 'Task', 'completed' => false, 'sort_order' => 0]);
    $msg = $project->messages()->create(['message' => 'Msg', 'admin' => 'Admin']);
    $projectId = $project->id;

    $this->actingAs($admin, 'admin')
         ->delete(route('admin.projects.destroy', $project));

    expect(Project::find($projectId))->toBeNull()
        ->and(ProjectTask::where('project_id', $projectId)->count())->toBe(0)
        ->and(ProjectMessage::where('project_id', $projectId)->count())->toBe(0);
});

test('projects index shows status filter', function () {
    $admin = Admin::factory()->create();
    Project::factory()->inProgress()->create(['title' => 'Active Project']);
    Project::factory()->completed()->create(['title' => 'Done Project']);

    $response = $this->actingAs($admin, 'admin')
         ->get(route('admin.projects.index', ['status' => 'in_progress']));

    $response->assertStatus(200)->assertSee('Active Project')->assertDontSee('Done Project');
});
