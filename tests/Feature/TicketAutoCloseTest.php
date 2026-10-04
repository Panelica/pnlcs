<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\TicketStatus;

/*
 * A ticket status could be marked auto_close, but nothing read the flag, no
 * screen could set it and there was no time limit to set: an Answered ticket
 * the customer never came back to stayed open for ever.
 */

function tacTicket(string $status, ?\DateTimeInterface $lastReply): Ticket
{
    return Ticket::factory()->create(['status' => $status, 'last_reply' => $lastReply]);
}

function tacAdmin(array $permissions = ['manage_ticket_config']): Admin
{
    return Admin::factory()->create(['role_id' => AdminRole::factory()->create(['name' => 'R'.uniqid(), 'permissions' => $permissions])->id]);
}

test('a ticket left unanswered in an auto-close status is closed, and the TicketClose hook runs', function () {
    TicketStatus::updateOrCreate(['title' => 'Answered'], ['color' => '#3b82f6', 'auto_close' => 1]);
    TicketStatus::updateOrCreate(['title' => 'Open'], ['color' => '#22c55e', 'auto_close' => 0]);
    Setting::set('TicketAutoCloseHours', '48');
    $stale = tacTicket('Answered', now()->subHours(49));
    $recent = tacTicket('Answered', now()->subHours(10));
    $open = tacTicket('Open', now()->subDays(30));
    $calls = 0;
    add_hook('TicketClose', function () use (&$calls) {
        $calls++;
    });

    $this->artisan('pnlcs:ticket-auto-close')->assertSuccessful();

    expect($stale->fresh()->status)->toBe('Closed')
        ->and($recent->fresh()->status)->toBe('Answered')
        ->and($open->fresh()->status)->toBe('Open')
        ->and($calls)->toBe(1);
});

test('nothing is closed while the time limit is 0', function () {
    TicketStatus::updateOrCreate(['title' => 'Answered'], ['color' => '#3b82f6', 'auto_close' => 1]);
    Setting::set('TicketAutoCloseHours', '0');
    $stale = tacTicket('Answered', now()->subYear());

    $this->artisan('pnlcs:ticket-auto-close')->assertSuccessful();

    expect($stale->fresh()->status)->toBe('Answered');
});

test('staff set the time limit and mark a status on the statuses screen', function () {
    $admin = tacAdmin();
    $this->actingAs($admin, 'admin')->post(route('admin.config.ticket-statuses.auto-close'), ['hours' => 72])->assertSessionHas('success');
    expect(Setting::get('TicketAutoCloseHours'))->toBe('72');

    $this->actingAs($admin, 'admin')->post(route('admin.config.ticket-statuses.store'), ['title' => 'Waiting on customer', 'color' => '#999999', 'auto_close' => 1]);
    $status = TicketStatus::where('title', 'Waiting on customer')->firstOrFail();
    expect((int) $status->auto_close)->toBe(1);

    $this->actingAs($admin, 'admin')->put(route('admin.config.ticket-statuses.update', $status), ['title' => 'Waiting on customer', 'color' => '#999999', 'sort_order' => 5]);
    expect((int) $status->fresh()->auto_close)->toBe(0);

    $this->actingAs($admin, 'admin')->get(route('admin.config.ticket-statuses'))->assertOk()->assertSee('value="72"', false);
    $this->actingAs(tacAdmin(['manage_announcements']), 'admin')->post(route('admin.config.ticket-statuses.auto-close'), ['hours' => 1])->assertForbidden();
});
