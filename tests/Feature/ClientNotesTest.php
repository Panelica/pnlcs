<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\ClientNote;

/**
 * Editing and deleting client notes.
 *
 * The routes sit in the edit_clients group and each action checks that the
 * note belongs to the client, so a note of another account cannot be touched
 * and a read-only admin is refused.
 */

function notesAdmin(bool $full = true): Admin
{
    return Admin::factory()->create([
        'role_id' => AdminRole::factory()->create($full ? ['is_full_admin' => true] : [])->id,
    ]);
}

function notedClient(array $overrides = []): Client
{
    return Client::factory()->create(array_merge([
        'first_name' => 'Deniz',
        'last_name' => 'Kaya',
        'email' => 'deniz@example.test',
        'country' => 'TR',
        'status' => 'active',
    ], $overrides));
}

function aClientNote(Client $client): ClientNote
{
    return ClientNote::create([
        'client_id' => $client->id,
        'admin' => 'Author Name',
        'note' => 'original note',
        'sticky' => false,
    ]);
}

test('an admin with edit_clients can edit a note and edited_by is set', function () {
    $client = notedClient();
    $note = aClientNote($client);

    $this->actingAs(notesAdmin(), 'admin')
        ->postJson(route('admin.clients.notes.update', [$client, $note]), ['note' => 'edited note'])
        ->assertOk()
        ->assertJsonPath('success', true);

    $note->refresh();
    expect($note->note)->toBe('edited note');
    expect($note->edited_by)->not->toBeNull();
});

test('a note of another client answers 404 for update and delete', function () {
    $client = notedClient();
    $other = notedClient(['email' => 'other@example.test']);
    $note = aClientNote($other);

    $this->actingAs(notesAdmin(), 'admin')
        ->postJson(route('admin.clients.notes.update', [$client, $note]), ['note' => 'x'])
        ->assertNotFound();

    $this->actingAs(notesAdmin(), 'admin')
        ->deleteJson(route('admin.clients.notes.destroy', [$client, $note]))
        ->assertNotFound();

    expect(ClientNote::find($note->id))->not->toBeNull();
});

test('an admin without edit_clients is refused', function () {
    $client = notedClient();
    $note = aClientNote($client);

    $this->actingAs(notesAdmin(false), 'admin')
        ->postJson(route('admin.clients.notes.update', [$client, $note]), ['note' => 'x'])
        ->assertForbidden();

    $this->actingAs(notesAdmin(false), 'admin')
        ->deleteJson(route('admin.clients.notes.destroy', [$client, $note]))
        ->assertForbidden();

    expect(ClientNote::find($note->id))->not->toBeNull();
});

test('delete removes the note', function () {
    $client = notedClient();
    $note = aClientNote($client);

    $this->actingAs(notesAdmin(), 'admin')
        ->deleteJson(route('admin.clients.notes.destroy', [$client, $note]))
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(ClientNote::find($note->id))->toBeNull();
});
