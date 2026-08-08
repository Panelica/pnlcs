<?php

use App\Models\Admin;
use App\Models\Client;
use App\Models\ClientNote;
use App\Services\ClientService;
use Illuminate\Http\Request;
use Modules\Addons\StaffBoard\StaffBoardModule;

/**
 * Admin notes losing the name of whoever wrote them.
 *
 * client_notes keeps its author in a plain `admin` name column - there is no
 * admin_id on that table and the model does not accept one. The admin screen
 * and ClientService both wrote 'admin_id' => <id>, which is not fillable, so it
 * was dropped on the way in and every note landed with no author at all. The
 * API door two files away writes the `admin` column and gets it right, which is
 * why notes added over the API carry a name and notes typed by staff do not.
 *
 * It shows: the staff board lists the author beside each note, so notes written
 * by real people appeared there as "System".
 */
function adminAddingNote(): Admin
{
    return Admin::factory()->create(['first_name' => 'Grace', 'last_name' => 'Hopper']);
}

it('records who wrote the note', function () {
    $client = Client::factory()->create();

    $this->actingAs(adminAddingNote(), 'admin')
        ->post(route('admin.clients.notes.store', $client), ['note' => 'Called about the invoice'])
        ->assertRedirect();

    expect(ClientNote::where('client_id', $client->id)->first()->admin)->toBe('Grace Hopper');
});

it('shows the author on the staff board instead of System', function () {
    $client = Client::factory()->create();

    $this->actingAs(adminAddingNote(), 'admin')
        ->post(route('admin.clients.notes.store', $client), ['note' => 'Called about the invoice']);

    expect((new StaffBoardModule)->output(Request::create('/')))->toContain('Grace Hopper');
});

it('still saves the note itself', function () {
    $client = Client::factory()->create();

    $this->actingAs(adminAddingNote(), 'admin')
        ->post(route('admin.clients.notes.store', $client), ['note' => 'Called about the invoice']);

    expect(ClientNote::where('client_id', $client->id)->first()->note)->toBe('Called about the invoice');
});

it('still pins a note when asked to', function () {
    $client = Client::factory()->create();

    $this->actingAs(adminAddingNote(), 'admin')
        ->post(route('admin.clients.notes.store', $client), ['note' => 'Read first', 'sticky' => 1]);

    expect((bool) ClientNote::where('client_id', $client->id)->first()->sticky)->toBeTrue();
});

it('leaves a note unpinned when nobody asked', function () {
    $client = Client::factory()->create();

    $this->actingAs(adminAddingNote(), 'admin')
        ->post(route('admin.clients.notes.store', $client), ['note' => 'Ordinary note']);

    expect((bool) ClientNote::where('client_id', $client->id)->first()->sticky)->toBeFalse();
});

it('still refuses an empty note', function () {
    $client = Client::factory()->create();

    $this->actingAs(adminAddingNote(), 'admin')
        ->post(route('admin.clients.notes.store', $client), ['note' => ''])
        ->assertSessionHasErrors('note');

    expect(ClientNote::where('client_id', $client->id)->count())->toBe(0);
});

it('records the author when the note comes through the service', function () {
    $client = Client::factory()->create();
    $admin = adminAddingNote();

    app(ClientService::class)->addNote($client, $admin, 'Service door note');

    expect(ClientNote::where('client_id', $client->id)->first()->admin)->toBe('Grace Hopper');
});
