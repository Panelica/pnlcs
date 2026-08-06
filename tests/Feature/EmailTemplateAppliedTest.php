<?php

use App\Mail\InvoiceCreatedMail;
use App\Models\Client;
use App\Models\Contact;
use App\Models\EmailTemplate;
use App\Models\Invoice;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

/**
 * The email templates screen edits 19 templates - subject, merge fields, a
 * disable switch, a from address, a copy-to address - and not one of the 23
 * mailables read any of it. An operator could switch the invoice email off,
 * save, and customers kept receiving it.
 */
function templatedInvoice(): Invoice
{
    $client = Client::factory()->create([
        'first_name' => 'Ayse',
        'last_name' => 'Yilmaz',
        'email' => 'ayse@example.com',
    ]);

    return Invoice::factory()->create([
        'client_id' => $client->id,
        'invoice_num' => 'INV-2026-77',
        'total' => 240,
        'status' => 'unpaid',
    ]);
}

function invoiceTemplate(array $attributes = []): EmailTemplate
{
    return EmailTemplate::updateOrCreate(
        ['name' => 'Invoice Created'],
        array_merge([
            'type' => 'invoice',
            'subject' => 'New Invoice #{invoice_num} - {CompanyName}',
            'message' => 'Dear {client_name}, amount due {invoice_total}.',
            'disabled' => false,
        ], $attributes)
    );
}

function capturedSends(): ArrayObject
{
    // An object, not an array: a returned array is a copy and the listener
    // would fill in something the test never sees.
    $sent = new ArrayObject;
    Event::listen(
        MessageSent::class,
        function ($event) use ($sent) {
            $sent->append($event->message->getSubject());
        }
    );

    return $sent;
}

test('switching a template off stops the email', function () {
    // Not Mail::fake(): the fake replaces the mailer, so the hook that applies
    // the template never runs and the test would prove nothing.
    invoiceTemplate(['disabled' => true]);
    $sent = capturedSends();

    Mail::to('ayse@example.com')->send(new InvoiceCreatedMail(templatedInvoice()));

    expect($sent->getArrayCopy())->toBe([]);
});

test('a template left on still sends', function () {
    invoiceTemplate(['disabled' => false]);
    $sent = capturedSends();

    Mail::to('ayse@example.com')->send(new InvoiceCreatedMail(templatedInvoice()));

    expect($sent->getArrayCopy())->toHaveCount(1);
});

test('the subject the operator wrote is the subject that goes out', function () {
    invoiceTemplate(['subject' => 'Fatura {invoice_num} - {client_name} ({invoice_total})']);
    $invoice = templatedInvoice();

    $sent = null;
    Mail::getSymfonyTransport();
    Event::listen(
        MessageSent::class,
        function ($event) use (&$sent) {
            $sent = $event->message->getSubject();
        }
    );

    Mail::to('ayse@example.com')->send(new InvoiceCreatedMail($invoice));

    expect($sent)->toBe('Fatura INV-2026-77 - Ayse Yilmaz (240.00)');
});

test('a copy goes to the address the operator added', function () {
    invoiceTemplate(['copy_to' => 'accounts@example.com, billing@example.com']);
    $invoice = templatedInvoice();

    $bcc = [];
    Event::listen(
        MessageSent::class,
        function ($event) use (&$bcc) {
            foreach ($event->message->getBcc() as $address) {
                $bcc[] = $address->getAddress();
            }
        }
    );

    Mail::to('ayse@example.com')->send(new InvoiceCreatedMail($invoice));

    expect($bcc)->toBe(['accounts@example.com', 'billing@example.com']);
});

test('the from address the operator set is used', function () {
    invoiceTemplate(['from_email' => 'billing@hosting.example', 'from_name' => 'Hosting Billing']);
    $invoice = templatedInvoice();

    $from = null;
    Event::listen(
        MessageSent::class,
        function ($event) use (&$from) {
            $addresses = $event->message->getFrom();
            $from = $addresses ? [$addresses[0]->getAddress(), $addresses[0]->getName()] : null;
        }
    );

    Mail::to('ayse@example.com')->send(new InvoiceCreatedMail($invoice));

    expect($from)->toBe(['billing@hosting.example', 'Hosting Billing']);
});

test('a mailable with no template of its own is untouched', function () {
    EmailTemplate::query()->delete();
    $sent = capturedSends();

    Mail::to('ayse@example.com')->send(new InvoiceCreatedMail(templatedInvoice()));

    expect($sent->getArrayCopy())->toHaveCount(1);
});

test('one mail hook does not silently cancel the next', function () {
    // MessageSending is dispatched with halting: the chain stops at the first
    // listener returning anything but null. A listener that returned true to
    // mean 'allowed' switched off every hook registered after it, which is how
    // the template listener came to do nothing at all.
    invoiceTemplate(['subject' => 'Chained {invoice_num}']);
    $invoice = templatedInvoice();

    $reachedLast = false;
    Event::listen(
        MessageSending::class,
        function () use (&$reachedLast) {
            $reachedLast = true;
        }
    );

    $subject = null;
    Event::listen(
        MessageSent::class,
        function ($event) use (&$subject) {
            $subject = $event->message->getSubject();
        }
    );

    Mail::to('ayse@example.com')->send(new InvoiceCreatedMail($invoice));

    expect($reachedLast)->toBeTrue()
        ->and($subject)->toBe('Chained INV-2026-77');
});

/**
 * A contact who is also the customer.
 *
 * Contacts who asked for this kind of email are copied in. The listener means
 * to skip anyone already on the message - it reads the existing recipients
 * into $already first - but it reads the keys of that list, which are 0, 1, 2,
 * not the addresses. So nobody is ever recognised, and a customer who added
 * their own address as a billing contact gets every invoice twice: once to
 * them, once copied to them.
 */
function ccAddressesFor(Invoice $invoice, string $to): array
{
    $cc = [];

    Event::listen(
        MessageSent::class,
        function ($event) use (&$cc) {
            foreach ($event->message->getCc() as $address) {
                $cc[] = strtolower($address->getAddress());
            }
        }
    );

    Mail::to($to)->send(new InvoiceCreatedMail($invoice));

    return $cc;
}

function contactOn(Invoice $invoice, string $email): Contact
{
    return Contact::create([
        'client_id' => $invoice->client_id,
        'first_name' => 'Copy',
        'last_name' => 'Recipient',
        'email' => $email,
        'invoice_emails' => true,
    ]);
}

test('a contact who is already the recipient is not copied in as well', function () {
    invoiceTemplate();
    $invoice = templatedInvoice();
    contactOn($invoice, 'ayse@example.com');

    $cc = ccAddressesFor($invoice, 'ayse@example.com');

    expect($cc)->not->toContain('ayse@example.com');
});

test('the same address in a different case is still the same person', function () {
    invoiceTemplate();
    $invoice = templatedInvoice();
    contactOn($invoice, 'AYSE@Example.Com');

    $cc = ccAddressesFor($invoice, 'ayse@example.com');

    expect($cc)->not->toContain('ayse@example.com');
});

test('a contact at their own address is still copied in', function () {
    invoiceTemplate();
    $invoice = templatedInvoice();
    contactOn($invoice, 'accounts@example.com');

    $cc = ccAddressesFor($invoice, 'ayse@example.com');

    expect($cc)->toContain('accounts@example.com');
});
