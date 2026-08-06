<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Announcement;

/**
 * An editor that opens empty.
 *
 * The announcement text lives in the "announcement" column - that is what the
 * customer's page prints, and what the create form is mapped onto. The edit
 * modal renders $ann->body, and there is no such attribute, so the textarea
 * comes up blank every time.
 *
 * It is also a required field. To correct a title, the operator has to retype
 * the whole announcement from memory, and whatever they type replaces the text
 * that was there - the update reads "body" first.
 */
function announcementsScreen(): string
{
    $role = AdminRole::factory()->create([
        'is_full_admin' => false,
        'permissions' => ['manage_announcements'],
    ]);

    $admin = Admin::factory()->create(['role_id' => $role->id]);

    return test()->actingAs($admin, 'admin')
        ->get(route('admin.config.announcements'))
        ->assertOk()
        ->getContent();
}

it('shows the announcement text in the editor', function () {
    Announcement::create([
        'title' => 'Maintenance',
        'announcement' => 'The London datacentre is offline between 02:00 and 04:00.',
        'published' => true,
    ]);

    expect(announcementsScreen())->toContain('The London datacentre is offline between 02:00 and 04:00.');
});

it('keeps the text when only the title is changed', function () {
    $announcement = Announcement::create([
        'title' => 'Maintenance',
        'announcement' => 'The London datacentre is offline between 02:00 and 04:00.',
        'published' => true,
    ]);

    $role = AdminRole::factory()->create(['is_full_admin' => false, 'permissions' => ['manage_announcements']]);
    $admin = Admin::factory()->create(['role_id' => $role->id]);

    test()->actingAs($admin, 'admin')->put(route('admin.config.announcements.update', $announcement), [
        'title' => 'Planned maintenance',
        'body' => 'The London datacentre is offline between 02:00 and 04:00.',
        'published' => '1',
    ])->assertRedirect();

    expect($announcement->fresh()->announcement)->toBe('The London datacentre is offline between 02:00 and 04:00.');
    expect($announcement->fresh()->title)->toBe('Planned maintenance');
});
