<?php

use App\Models\Admin;
use App\Models\TodoItem;

/*
 * The to-do list showed an "Admin" column that nothing could fill: the add
 * form had no field for it and the controller dropped it, so every task read
 * "-" and nobody could see which ones were theirs.
 */

test('a task can be given to a member of staff, and they can list theirs', function () {
    $mert = Admin::factory()->create(['username' => 'mert']);
    $selin = Admin::factory()->create(['username' => 'selin']);

    $this->actingAs($mert, 'admin')->post(route('admin.config.todo.store'), ['title' => 'Renew SSL', 'admin' => 'selin'])->assertRedirect();
    $this->actingAs($mert, 'admin')->post(route('admin.config.todo.store'), ['title' => 'Check backups'])->assertRedirect();
    expect(TodoItem::where('title', 'Renew SSL')->value('admin'))->toBe('selin')
        ->and(TodoItem::where('title', 'Check backups')->value('admin'))->toBeNull();

    $this->actingAs($selin, 'admin')->get(route('admin.config.todo', ['mine' => 1]))->assertOk()->assertSee('Renew SSL')->assertDontSee('Check backups');
    $this->actingAs($selin, 'admin')->get(route('admin.config.todo'))->assertOk()->assertSee('Check backups')->assertSee('name="admin"', false);
});

test('a task is given only to someone who exists, and completing it keeps who it was for', function () {
    $mert = Admin::factory()->create(['username' => 'mert']);

    $this->actingAs($mert, 'admin')->from(route('admin.config.todo'))
        ->post(route('admin.config.todo.store'), ['title' => 'X', 'admin' => 'nobody-here'])->assertSessionHasErrors('admin');

    $todo = TodoItem::create(['title' => 'Y', 'admin' => 'mert']);
    $this->actingAs($mert, 'admin')->put(route('admin.config.todo.update', $todo), ['title' => 'Y', 'status' => 'Completed'])->assertRedirect();
    expect($todo->fresh())->status->toBe('Completed')->admin->toBe('mert');
});
