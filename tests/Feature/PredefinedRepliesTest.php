<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use App\Models\TicketPredefinedCategory;
use App\Models\TicketPredefinedReply;

/*
 * Predefined replies: staff save answers and insert them into a reply.
 *
 * The tables, models and the API's read calls existed; nothing could add one,
 * and the reply form had no way to use them.
 */

function prAdmin(array $permissions = ['manage_ticket_config', 'view_tickets', 'list_tickets', 'reply_tickets']): Admin
{
    return Admin::factory()->create(['role_id' => AdminRole::factory()->create(['name' => 'R'.uniqid(), 'permissions' => $permissions])->id]);
}

it('adds, edits and deletes categories and replies', function () {
    $admin = prAdmin();

    test()->actingAs($admin, 'admin')->post(route('admin.config.predefined-replies.categories.store'), ['name' => 'DNS'])->assertSessionHas('success');
    $category = TicketPredefinedCategory::where('name', 'DNS')->sole();

    test()->actingAs($admin, 'admin')->post(route('admin.config.predefined-replies.store'), [
        'category_id' => $category->id, 'name' => 'Propagation', 'reply' => 'DNS changes can take up to 24 hours.',
    ])->assertSessionHas('success');
    $reply = TicketPredefinedReply::where('name', 'Propagation')->sole();

    test()->actingAs($admin, 'admin')->put(route('admin.config.predefined-replies.update', $reply), [
        'category_id' => $category->id, 'name' => 'Propagation', 'reply' => 'DNS changes can take up to 48 hours.',
    ])->assertSessionHas('success');
    expect($reply->fresh()->reply)->toBe('DNS changes can take up to 48 hours.');

    test()->actingAs($admin, 'admin')->get(route('admin.config.predefined-replies'))->assertOk()->assertSee('Propagation');

    test()->actingAs($admin, 'admin')->delete(route('admin.config.predefined-replies.categories.destroy', $category))->assertSessionHas('success');
    expect(TicketPredefinedReply::count())->toBe(0);
});

it('offers the saved replies on the ticket reply form', function () {
    $category = TicketPredefinedCategory::create(['name' => 'Billing']);
    TicketPredefinedReply::create(['category_id' => $category->id, 'name' => 'Bank details', 'reply' => 'Our IBAN is in the invoice footer.']);
    $ticket = Ticket::create(['tid' => '445566', 'client_id' => Client::factory()->create()->id,
        'department_id' => TicketDepartment::create(['name' => 'Support', 'hidden' => false, 'sort_order' => 1])->id,
        'name' => 'Ayşe', 'email' => 'ayse@example.test', 'title' => 'Where to pay', 'message' => 'x', 'status' => 'Open', 'priority' => 'Medium']);

    test()->actingAs(prAdmin(), 'admin')->get(route('admin.tickets.show', $ticket))->assertOk()
        ->assertSee('id="predefined-reply"', false)
        ->assertSee('data-reply="Our IBAN is in the invoice footer."', false);
});

it('keeps the screen to staff who configure support', function () {
    test()->actingAs(prAdmin(['view_tickets', 'reply_tickets']), 'admin')->get(route('admin.config.predefined-replies'))->assertForbidden();
});
