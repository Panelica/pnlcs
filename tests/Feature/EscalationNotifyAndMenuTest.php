<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use App\Models\TicketEscalation;
use App\Services\NotificationService;
use App\Services\TicketEscalationService;

/*
 * Two things from the escalation screen and the admin menu.
 *
 * An escalation rule's "Send notification on escalation" was saved and never
 * read: a ticket left unanswered past the rule's time was reassigned and noted,
 * and nobody was told. It now raises ticket.escalated through the notification
 * rules, where staff choose who hears it and how.
 *
 * And three admin pages had no menu entry at all - Ticket Escalation Rules,
 * Configurable Options and Mass Email - so only someone who knew the address
 * (or found the button on a Proxmox product) could reach them.
 */

function enmTicket(): Ticket
{
    return Ticket::factory()->create([
        'client_id' => Client::factory()->create()->id, 'department_id' => TicketDepartment::factory()->create()->id,
        'status' => 'Open', 'flag' => null, 'last_reply' => now()->subHours(5), 'title' => 'Site down',
    ]);
}

test('a rule with notify raises ticket.escalated, once, and one without does not', function (bool $notify, int $times) {
    $ticket = enmTicket();
    TicketEscalation::create(['name' => 'Slow', 'time_elapsed' => 60, 'statuses' => ['Open'], 'notify' => $notify, 'new_priority' => 'High']);
    $sent = [];
    $spy = Mockery::mock(NotificationService::class)->makePartial();
    $spy->shouldReceive('dispatch')->andReturnUsing(function ($event, $data) use (&$sent) {
        $sent[] = [$event, $data];
    });
    app()->instance(NotificationService::class, $spy);

    app(TicketEscalationService::class)->checkAndEscalate();
    app(TicketEscalationService::class)->checkAndEscalate();

    expect($sent)->toHaveCount($times);
    if ($times) {
        expect($sent[0][0])->toBe('ticket.escalated')
            ->and($sent[0][1]['ticket_id'])->toBe($ticket->id)
            ->and($sent[0][1]['message'])->toContain('Site down')->toContain('priority changed to High');
    }
})->with([
    'notify on' => [true, 1],
    'notify off' => [false, 0],
]);

test('ticket.escalated can be chosen for a notification rule', function () {
    expect(collect(NotificationService::EVENT_TYPES)->flatten()->all())->toContain('ticket.escalated');
});

test('the admin menu reaches the three pages that had no entry', function () {
    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);

    $this->actingAs($admin, 'admin')->get(route('admin.dashboard'))->assertOk()
        ->assertSee(route('admin.config.ticket-escalation'), false)
        ->assertSee(route('admin.config.config-options'), false)
        ->assertSee(route('admin.bulk.mass-email'), false);
});
