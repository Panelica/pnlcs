<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use App\Models\TicketFeedback;
use App\Models\TicketReply;
use App\Models\User;
use Modules\Reports\TicketRatingsReport;

/*
 * The customer rates a closed ticket.
 *
 * ticket_feedback and two Support reports reading it ("Ticket Feedback
 * Scores", "Ticket Ratings") existed; nothing let a customer give a rating,
 * so the reports were always empty.
 */

function tfTicket(string $status = 'Closed'): array
{
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id, ['owner' => true]);
    $ticket = Ticket::create(['tid' => (string) random_int(100000, 999999), 'client_id' => $client->id,
        'department_id' => TicketDepartment::create(['name' => 'Support', 'hidden' => false, 'sort_order' => 1])->id,
        'name' => 'Ayşe', 'email' => 'ayse@example.test', 'title' => 'Mail bounces', 'message' => 'x', 'status' => $status, 'priority' => 'Medium']);

    return [$user, $ticket];
}

it('asks for a rating once the ticket is closed', function () {
    [$user, $ticket] = tfTicket();

    test()->actingAs($user)->get(route('client.tickets.show', $ticket))
        ->assertOk()->assertSee(route('client.tickets.feedback', $ticket), false);

    [$user2, $open] = tfTicket('Open');
    test()->actingAs($user2)->get(route('client.tickets.show', $open))->assertDontSee(route('client.tickets.feedback', $open), false);
});

it('keeps one rating per ticket, with the comment', function () {
    [$user, $ticket] = tfTicket();

    test()->actingAs($user)->post(route('client.tickets.feedback', $ticket), ['rating' => 4, 'comments' => 'Quick and clear.'])->assertSessionHas('success');
    test()->actingAs($user)->post(route('client.tickets.feedback', $ticket), ['rating' => 1])->assertSessionHas('error');

    $feedback = TicketFeedback::where('ticket_id', $ticket->id)->sole();
    expect($feedback->rating)->toBe(4)->and($feedback->comments)->toBe('Quick and clear.');
    test()->actingAs($user)->get(route('client.tickets.show', $ticket))->assertSee(__('client.tickets.feedback_given', ['rating' => 4]));
});

it('refuses an open ticket, a rating out of range, and another customer', function () {
    [$user, $open] = tfTicket('Open');
    test()->actingAs($user)->post(route('client.tickets.feedback', $open), ['rating' => 5])->assertSessionHas('error');

    [$user2, $closed] = tfTicket();
    test()->actingAs($user2)->post(route('client.tickets.feedback', $closed), ['rating' => 6])->assertSessionHasErrors('rating');
    test()->actingAs($user)->post(route('client.tickets.feedback', $closed), ['rating' => 5])->assertForbidden();

    expect(TicketFeedback::count())->toBe(0);
});

it('shows the rating to staff and counts each ticket once per staff member', function () {
    [$user, $ticket] = tfTicket();
    foreach (['Mert', 'Mert', 'Mert'] as $staff) {
        TicketReply::create(['ticket_id' => $ticket->id, 'admin' => $staff, 'message' => 'reply']);
    }
    test()->actingAs($user)->post(route('client.tickets.feedback', $ticket), ['rating' => 5]);

    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->create(['name' => 'S', 'permissions' => ['view_tickets', 'list_tickets']])->id]);
    test()->actingAs($admin, 'admin')->get(route('admin.tickets.show', $ticket))->assertOk()->assertSee(__('admin.tickets.feedback_rating', ['rating' => 5]));

    $rows = (new TicketRatingsReport)->generate(request())['rows'];
    expect(collect($rows)->firstWhere('staff', 'Mert')->reviews)->toBe(1);
});
