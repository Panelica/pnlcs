<?php

use App\Models\Announcement;
use App\Models\Client;
use App\Models\DownloadCategory;
use App\Models\TicketDepartment;
use App\Models\User;

test('announcements index is publicly accessible', function () {
    Announcement::factory()->count(3)->create(['published' => true]);

    $this->get(route('client.announcements.index'))
        ->assertStatus(200)
        ->assertSee('Announcements');
});

test('draft announcements are not shown', function () {
    $draft = Announcement::factory()->create(['published' => false, 'title' => 'Secret Draft']);

    $this->get(route('client.announcements.index'))
        ->assertStatus(200)
        ->assertDontSee('Secret Draft');
});

test('single announcement page is publicly accessible', function () {
    $announcement = Announcement::factory()->create([
        'published' => true,
        'title'     => 'Important Update',
    ]);

    $this->get(route('client.announcements.show', $announcement))
        ->assertStatus(200)
        ->assertSee('Important Update');
});

test('draft announcement show returns 404', function () {
    $draft = Announcement::factory()->create(['published' => false]);

    $this->get(route('client.announcements.show', $draft))
        ->assertStatus(404);
});

test('contact form is publicly accessible', function () {
    TicketDepartment::factory()->create(['name' => 'Sales', 'hidden' => false]);

    $this->get(route('client.contact'))
        ->assertStatus(200)
        ->assertSee('Contact Us');
});

test('contact form can be submitted by guests', function () {
    $dept = TicketDepartment::factory()->create(['hidden' => false]);

    $response = $this->post(route('client.contact.submit'), [
        'name'          => 'John Doe',
        'email'         => 'john@example.com',
        'department_id' => $dept->id,
        'subject'       => 'Test inquiry',
        'message'       => 'This is a test message from a guest.',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('tickets', [
        'email'  => 'john@example.com',
        'title'  => 'Test inquiry',
        'status' => 'Open',
    ]);
});

test('contact form validates required fields', function () {
    $this->post(route('client.contact.submit'), [])
        ->assertSessionHasErrors(['name', 'email', 'department_id', 'subject', 'message']);
});

test('downloads page requires authentication', function () {
    // Unauthenticated request should redirect (to login page)
    $this->get(route('client.downloads.index'))
        ->assertRedirect();
});

test("authenticated user can view downloads", function () {
    $user = User::factory()->create();
    $this->actingAs($user)
        ->get(route('client.downloads.index'))
        ->assertStatus(200);
});

test('add funds page requires authentication', function () {
    // Unauthenticated request should redirect (to login page)
    $this->get(route('client.funds.index'))
        ->assertRedirect();
});

test('authenticated user can view add funds page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('client.funds.index'))
        ->assertStatus(200)
        ->assertSee('Add Funds');
});
