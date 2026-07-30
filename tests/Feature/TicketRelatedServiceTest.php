<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * The service a ticket is about.
 *
 * The new-ticket form asks which service the customer is writing about, and
 * the answer went nowhere: the controller neither validated nor stored it, and
 * no screen showed it. Support opened a ticket saying "my site is down" with
 * nothing to say which of the customer's four sites was meant.
 */
function ticketCustomer(): array
{
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);

    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'name' => 'Business Hosting',
    ]);

    $service = Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => $product->id,
        'domain' => 'affected-site.com',
        'status' => 'active',
    ]);

    TicketDepartment::factory()->create(['hidden' => false]);

    return [$user, $client, $service];
}

test('the service the customer picked is kept', function () {
    Mail::fake();
    [$user, , $service] = ticketCustomer();

    $this->actingAs($user)->post(route('client.tickets.store'), [
        'department_id' => TicketDepartment::first()->id,
        'subject' => 'Site is down',
        'message' => 'It has been down since this morning.',
        'related_service' => $service->id,
    ])->assertRedirect();

    expect(Ticket::latest('id')->first()->service)->toBe((string) $service->id);
});

test('a ticket about nothing in particular still opens', function () {
    Mail::fake();
    [$user] = ticketCustomer();

    $this->actingAs($user)->post(route('client.tickets.store'), [
        'department_id' => TicketDepartment::first()->id,
        'subject' => 'Billing question',
        'message' => 'How do I change my card?',
        'related_service' => '',
    ])->assertRedirect();

    expect(Ticket::count())->toBe(1)
        ->and(Ticket::first()->service)->toBeNull();
});

test('somebody elses service cannot be attached to a ticket', function () {
    Mail::fake();
    [$user] = ticketCustomer();

    $stranger = Service::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'domain' => 'not-mine.com',
    ]);

    $this->actingAs($user)->post(route('client.tickets.store'), [
        'department_id' => TicketDepartment::first()->id,
        'subject' => 'Nosy',
        'message' => 'Whose is this?',
        'related_service' => $stranger->id,
    ])->assertSessionHasErrors('related_service');

    expect(Ticket::count())->toBe(0);
});

test('support can see which service the ticket is about', function () {
    Mail::fake();
    [$user, $client, $service] = ticketCustomer();

    $this->actingAs($user)->post(route('client.tickets.store'), [
        'department_id' => TicketDepartment::first()->id,
        'subject' => 'Site is down',
        'message' => 'Since this morning.',
        'related_service' => $service->id,
    ])->assertRedirect();

    $admin = Admin::factory()->create([
        'role_id' => AdminRole::factory()->create([
            'name' => 'Support',
            'permissions' => ['list_tickets', 'view_tickets'],
        ])->id,
    ]);

    $this->actingAs($admin, 'admin')
        ->get(route('admin.tickets.show', Ticket::latest('id')->first()))
        ->assertOk()
        ->assertSee('affected-site.com');
});
