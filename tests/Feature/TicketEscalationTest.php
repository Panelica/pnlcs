<?php

use App\Models\Client;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use App\Models\TicketEscalation;
use App\Models\User;
use App\Services\TicketEscalationService;

/**
 * An escalation rule that only sends an auto-reply never marked the ticket as
 * escalated, because the marker was written solely when the rule reassigned the
 * ticket. The "skip already-escalated" filter therefore never matched and the
 * rule re-fired every cycle: proven at runtime, three runs produced three
 * identical auto-replies to the customer. With the cron running every fifteen
 * minutes that is an endless mail loop.
 */
function escalationTicket(array $attrs = []): Ticket
{
    return Ticket::factory()->create(array_merge([
        'client_id' => Client::factory()->create()->id,
        'department_id' => TicketDepartment::factory()->create()->id,
        'status' => 'Open',
        'flag' => null,
        'last_reply' => now()->subHours(5),
    ], $attrs));
}

test('an auto-reply rule fires once, not on every cycle', function () {
    $ticket = escalationTicket();
    TicketEscalation::create([
        'name' => 'Chase customer',
        'time_elapsed' => 60,
        'statuses' => ['Open'],
        'add_reply' => 'Are you still there?',
    ]);

    $service = app(TicketEscalationService::class);

    expect($service->checkAndEscalate())->toBe(1)
        ->and($ticket->fresh()->replies()->count())->toBe(1)
        ->and($ticket->fresh()->escalated_at)->not->toBeNull();

    // Customer stays silent and another period passes.
    Ticket::whereKey($ticket->id)->update(['last_reply' => now()->subMinutes(61)]);

    expect($service->checkAndEscalate())->toBe(0)
        ->and($ticket->fresh()->replies()->count())->toBe(1);
});

test('a customer reply clears the marker so a later silence escalates again', function () {
    $ticket = escalationTicket();
    TicketEscalation::create([
        'name' => 'Chase customer',
        'time_elapsed' => 60,
        'statuses' => ['Open', 'Customer-Reply'],
        'add_reply' => 'Are you still there?',
    ]);
    $service = app(TicketEscalationService::class);
    $service->checkAndEscalate();

    $user = User::factory()->create();
    $user->clients()->attach($ticket->client_id);

    $this->actingAs($user)
        ->post(route('client.tickets.reply', $ticket), ['message' => 'Still waiting'])
        ->assertRedirect();

    expect($ticket->fresh()->escalated_at)->toBeNull()
        ->and($ticket->fresh()->status)->toBe('Customer-Reply');

    // Silence again → the rule may act once more.
    Ticket::whereKey($ticket->id)->update(['last_reply' => now()->subMinutes(61)]);

    expect($service->checkAndEscalate())->toBe(1)
        ->and($ticket->fresh()->replies()->count())->toBe(3); // customer + 2 auto-replies
});

test('an assignment rule still assigns and marks the ticket', function () {
    $ticket = escalationTicket();
    TicketEscalation::create([
        'name' => 'Escalate to lead',
        'time_elapsed' => 60,
        'statuses' => ['Open'],
        'flag_to' => '7',
        'new_priority' => 'High',
    ]);

    expect(app(TicketEscalationService::class)->checkAndEscalate())->toBe(1);

    $fresh = $ticket->fresh();
    expect($fresh->admin)->toBe('7')
        ->and($fresh->priority)->toBe('High')
        ->and($fresh->flag)->toBe(7)
        ->and($fresh->escalated_at)->not->toBeNull();
});
