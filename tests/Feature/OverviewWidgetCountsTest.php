<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use App\Widgets\OverviewWidget;

/**
 * The two numbers on the front page that were lower than the truth.
 *
 * A customer who answers a staff reply moves the ticket to Customer-Reply. It
 * is waiting on staff exactly as much as an Open one, which is why the Support
 * widget, in the same folder, counts both under "Awaiting Reply". The Overview
 * tile labelled "Open Tickets" counted only Open, so the queue on the front page
 * was smaller than the queue on the ticket screen it links to.
 *
 * "Unpaid Invoices" had the same shape: it counted status unpaid and left out
 * overdue, which is what an unpaid invoice becomes the day after it is due. The
 * longer a customer failed to pay, the less the front page said was owed. The
 * rest of the panel treats the two together - unsuspend-on-payment asks for
 * unpaid or overdue when it wants to know whether anything is still outstanding.
 */
function overviewTicket(string $status): Ticket
{
    return Ticket::create([
        'tid' => (string) random_int(100000, 999999),
        'department_id' => TicketDepartment::create(['name' => 'Support '.uniqid()])->id,
        'client_id' => Client::factory()->create()->id,
        'name' => 'Aylin Kaya',
        'email' => 'aylin@example.test',
        'title' => 'Something is wrong',
        'message' => 'Please look.',
        'status' => $status,
        'priority' => 'medium',
        'last_reply' => now(),
    ]);
}

function overviewInvoice(string $status): Invoice
{
    return Invoice::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'status' => $status,
        'total' => 50,
    ]);
}

it('counts a ticket the customer has answered as open', function () {
    overviewTicket('Open');
    overviewTicket('Customer-Reply');
    overviewTicket('Closed');

    expect((new OverviewWidget)->getData()['tickets_open'])->toBe(2);
});

it('counts an overdue invoice among the unpaid ones', function () {
    overviewInvoice('unpaid');
    overviewInvoice('overdue');
    overviewInvoice('paid');

    expect((new OverviewWidget)->getData()['invoices_unpaid'])->toBe(2);
});

it('still leaves a closed ticket and a paid invoice out', function () {
    overviewTicket('Closed');
    overviewInvoice('paid');

    $data = (new OverviewWidget)->getData();

    expect($data['tickets_open'])->toBe(0)
        ->and($data['invoices_unpaid'])->toBe(0);
});

it('still counts what it always counted', function () {
    $client = Client::factory()->create();

    expect((new OverviewWidget)->getData()['clients'])->toBeGreaterThan(0);
});
