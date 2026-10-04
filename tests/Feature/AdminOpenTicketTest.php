<?php

use App\Events\TicketOpened;
use App\Mail\TicketOpenedMail;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

/*
 * Staff open a ticket for a customer.
 *
 * Nothing in the admin area could: the client page's "New ticket" link went
 * to the ticket list, and a customer who rang support had to be asked to
 * open the ticket themselves.
 */

function aotAdmin(array $permissions = ['list_tickets', 'view_tickets', 'reply_tickets']): Admin
{
    return Admin::factory()->create(['username' => 'ayse'.uniqid(), 'role_id' => AdminRole::factory()->create(['name' => 'R'.uniqid(), 'permissions' => $permissions])->id]);
}

it('opens a ticket for the client from their page, as Answered, and tells them staff opened it', function () {
    Mail::fake();
    $admin = aotAdmin(['list_tickets', 'view_tickets', 'reply_tickets', 'view_clients']);
    $client = Client::factory()->create(['first_name' => 'Mehmet', 'last_name' => 'Demir', 'email' => 'mehmet@example.test']);
    $service = Service::factory()->create(['client_id' => $client->id]);
    $dept = TicketDepartment::factory()->create();

    test()->actingAs($admin, 'admin')->get(route('admin.clients.show', $client))->assertSee(route('admin.tickets.create', ['client' => $client->id]), false);
    test()->actingAs($admin, 'admin')->get(route('admin.tickets.create', ['client' => $client->id]))->assertOk()->assertSee('mehmet@example.test');

    test()->actingAs($admin, 'admin')->post(route('admin.tickets.store'), [
        'client' => (string) $client->id, 'department_id' => $dept->id, 'priority' => 'High', 'related_service' => $service->id,
        'subject' => 'Your SSL renewal', 'message' => 'We need a DNS change from you.',
    ])->assertRedirect();

    $ticket = Ticket::where('title', 'Your SSL renewal')->firstOrFail();
    expect($ticket)->client_id->toBe($client->id)->email->toBe('mehmet@example.test')->status->toBe('Answered')
        ->priority->toBe('High')->admin->toBe($admin->username)->service->toBe((string) $service->id);
    Mail::assertQueued(TicketOpenedMail::class, fn ($m) => $m->byStaff && ! $m->isAdmin && $m->hasTo('mehmet@example.test'));
    Mail::assertQueued(TicketOpenedMail::class, 1);
});

it('finds the client by email address, and refuses an unknown client or another client\'s service', function () {
    Event::fake([TicketOpened::class]);
    $admin = aotAdmin();
    $client = Client::factory()->create(['email' => 'zeynep@example.test']);
    $other = Service::factory()->create(['client_id' => Client::factory()->create()->id]);
    $dept = TicketDepartment::factory()->create();
    $base = ['department_id' => $dept->id, 'priority' => 'Medium', 'subject' => 'Hello', 'message' => 'Text'];

    test()->actingAs($admin, 'admin')->post(route('admin.tickets.store'), $base + ['client' => 'nobody@example.test'])->assertSessionHasErrors('client');
    test()->actingAs($admin, 'admin')->post(route('admin.tickets.store'), $base + ['client' => 'zeynep@example.test', 'related_service' => $other->id])->assertSessionHasErrors('related_service');
    test()->actingAs($admin, 'admin')->post(route('admin.tickets.store'), $base + ['client' => 'zeynep@example.test'])->assertRedirect();

    expect(Ticket::where('client_id', $client->id)->count())->toBe(1);
    Event::assertDispatched(TicketOpened::class, 1);
});

it('is for staff who may reply to tickets', function () {
    test()->actingAs(aotAdmin(['list_tickets', 'view_tickets']), 'admin')->get(route('admin.tickets.create'))->assertForbidden();
});

it('tells a customer who opened their own ticket the usual words', function () {
    $ticket = Ticket::factory()->create(['email' => 'c@example.test']);

    expect((new TicketOpenedMail($ticket))->render())->toContain(__('email.ticket_opened.client_success'))
        ->and((new TicketOpenedMail($ticket, false, true))->render())->toContain(__('email.ticket_opened.staff_opened'));
});
