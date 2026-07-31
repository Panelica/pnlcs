<?php

use App\Mail\InvoiceCreatedMail;
use App\Mail\PaymentReminderMail;
use App\Mail\TicketOpenedMail;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

/**
 * Being able to act on the email you were sent.
 *
 * Twenty-four transactional emails and one of them — the password reset — had
 * a link in it. The rest told the customer to log in and gave them nothing to
 * click. The payment reminder managed "Please log in to your account to view
 * the details. to make a payment.": two sentences glued together with the
 * link that belonged between them missing altogether.
 *
 * Six of them also printed amounts with a dollar sign whatever currency the
 * shop sells in.
 */
function mailBodies(): ArrayObject
{
    $bodies = new ArrayObject;

    Event::listen(MessageSent::class, function ($event) use ($bodies) {
        $bodies->append(quoted_printable_decode($event->message->getBody()->bodyToString()));
    });

    return $bodies;
}

function billedInvoice(float $total = 263.82): Invoice
{
    return Invoice::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'invoice_num' => 'INV-LINK-1',
        'status' => 'unpaid',
        'total' => $total,
        'due_date' => now()->addDays(3),
    ]);
}

test('a reminder gives the customer somewhere to pay', function () {
    $invoice = billedInvoice();
    $bodies = mailBodies();

    Mail::to($invoice->client->email)->send(new PaymentReminderMail($invoice, 3));

    $body = implode(' ', $bodies->getArrayCopy());

    expect($body)->toContain(route('client.invoices.show', $invoice->id))
        // The sentence that was left hanging when the link went missing.
        ->and($body)->not->toContain('the details. to make a payment');
});

test('an invoice email links to the invoice', function () {
    $invoice = billedInvoice();
    $bodies = mailBodies();

    Mail::to($invoice->client->email)->send(new InvoiceCreatedMail($invoice));

    expect(implode(' ', $bodies->getArrayCopy()))
        ->toContain(route('client.invoices.show', $invoice->id));
});

test('a ticket email links to the ticket', function () {
    $ticket = Ticket::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'department_id' => TicketDepartment::factory()->create()->id,
    ]);

    $bodies = mailBodies();

    Mail::to($ticket->client->email)->send(new TicketOpenedMail($ticket));

    expect(implode(' ', $bodies->getArrayCopy()))
        ->toContain(route('client.tickets.show', $ticket->id));
});

test('amounts are shown in the currency being sold in', function () {
    Currency::query()->update(['is_default' => false]);
    Currency::updateOrCreate(
        ['code' => 'EUR'],
        ['prefix' => '€', 'suffix' => '', 'rate' => 1, 'is_default' => true]
    );

    $invoice = billedInvoice(150);
    $bodies = mailBodies();

    Mail::to($invoice->client->email)->send(new PaymentReminderMail($invoice, 3));

    $body = implode(' ', $bodies->getArrayCopy());

    expect($body)->toContain('€150.00')
        ->and($body)->not->toContain('$150.00');
});
