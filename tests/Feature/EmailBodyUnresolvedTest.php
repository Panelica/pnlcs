<?php

use App\Mail\InvoiceCreatedMail;
use App\Mail\PasswordResetMail;
use App\Models\Client;
use App\Models\EmailTemplate;
use App\Models\Invoice;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

/**
 * Merge fields the email carries nothing for, reaching the customer.
 *
 * The listener already refuses to send a subject with braces left in it: it
 * keeps the subject the mailable wrote and says why in the log. The body two
 * dozen lines below asks no such question - whatever the merge left behind went
 * out as written.
 *
 * The password reset is where this bites. That mailable carries a link and an
 * address and no client at all, so {client_name} - which is in the seeded body
 * of that very template - resolved to nothing, and the customer trying to get
 * back into their account was greeted by name as "{client_name}".
 *
 * So both halves: the client is looked up from the address the email is going
 * to when nothing else identifies them, and a body still holding a merge field
 * falls back to the one the mailable built, exactly as the subject does.
 */
function unresolvedBodies(): ArrayObject
{
    $bodies = new ArrayObject;

    Event::listen(MessageSent::class, function ($event) use ($bodies) {
        $bodies->append(quoted_printable_decode($event->message->getBody()->bodyToString()));
    });

    return $bodies;
}

function unresolvedTemplate(string $name, string $type, string $body, bool $custom = true): void
{
    EmailTemplate::updateOrCreate(
        ['name' => $name],
        ['type' => $type, 'subject' => $name, 'message' => $body, 'disabled' => false, 'custom' => $custom]
    );
}

function unresolvedInvoice(): Invoice
{
    return Invoice::factory()->create([
        'client_id' => Client::factory()->create(['email' => 'ayse@example.test'])->id,
        'invoice_num' => 'INV-2026-91',
        'total' => 120,
        'status' => 'unpaid',
    ]);
}

it('greets a password reset by name rather than by merge field', function () {
    $client = Client::factory()->create(['first_name' => 'Nadia', 'last_name' => 'Okonkwo', 'email' => 'nadia@example.test']);
    unresolvedTemplate('Password Reset Confirmation', 'general', 'Hello {client_name}, use {reset_url}');

    $bodies = unresolvedBodies();
    Mail::to($client->email)->send(new PasswordResetMail('https://panel.example/reset/xyz', $client->email));

    expect(implode(' ', $bodies->getArrayCopy()))->toContain('Nadia Okonkwo');
});

it('does not send a body with a merge field still showing', function () {
    unresolvedTemplate('Invoice Created', 'invoice', 'Dear {client_name}, about {nothing_feeds_this}.');

    $bodies = unresolvedBodies();
    Mail::to('ayse@example.test')->send(new InvoiceCreatedMail(unresolvedInvoice()));

    expect(implode(' ', $bodies->getArrayCopy()))->not->toContain('{nothing_feeds_this}');
});

it('still sends the reset link when nobody owns that address', function () {
    unresolvedTemplate('Password Reset Confirmation', 'general', 'Hello {client_name}, use {reset_url}');

    $bodies = unresolvedBodies();
    Mail::to('stranger@example.test')->send(new PasswordResetMail('https://panel.example/reset/abc', 'stranger@example.test'));

    expect(implode(' ', $bodies->getArrayCopy()))->toContain('https://panel.example/reset/abc');
});

it('still replaces the body when every field is fed', function () {
    unresolvedTemplate('Invoice Created', 'invoice', 'Your invoice {invoice_num} is ready.');

    $bodies = unresolvedBodies();
    Mail::to('ayse@example.test')->send(new InvoiceCreatedMail(unresolvedInvoice()));

    expect(implode(' ', $bodies->getArrayCopy()))->toContain('is ready');
});

it('still leaves an untouched template to the built-in design', function () {
    unresolvedTemplate('Invoice Created', 'invoice', 'Plain seeded text {client_name}', custom: false);

    $bodies = unresolvedBodies();
    Mail::to('ayse@example.test')->send(new InvoiceCreatedMail(unresolvedInvoice()));

    expect(implode(' ', $bodies->getArrayCopy()))->not->toContain('Plain seeded text');
});
