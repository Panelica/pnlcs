<?php

use App\Mail\CancellationConfirmMail;
use App\Mail\DomainRegistrationMail;
use App\Mail\DomainRenewalReminderMail;
use App\Mail\InvoiceCreatedMail;
use App\Mail\InvoiceOverdueMail;
use App\Mail\InvoicePaidMail;
use App\Mail\OrderConfirmationMail;
use App\Mail\PaymentReminderMail;
use App\Mail\ServiceSuspensionMail;
use App\Mail\ServiceTerminationMail;
use App\Mail\ServiceUnsuspensionMail;
use App\Mail\ServiceWelcomeMail;
use App\Mail\TicketOpenedMail;
use App\Mail\TicketReplyMail;
use App\Models\Client;
use App\Models\Domain;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\TicketDepartment;

/**
 * The email templates render in the customer's language (LocalizesToRecipient),
 * but the subjects were hardcoded in English in each mailable's envelope(), so
 * a Polish or German customer got the body in their language and the subject
 * in English. The subjects now read email.* keys, resolved in the recipient's
 * locale like the body.
 */

/** The invoice mails and the key + variables their subjects are built from. */
function invoiceMailCases(Invoice $invoice): array
{
    $num = $invoice->invoice_num;

    return [
        'created' => [new InvoiceCreatedMail($invoice), 'email.invoice.created_subject', ['number' => $num]],
        'overdue' => [new InvoiceOverdueMail($invoice, 5), 'email.invoice.overdue_subject', ['number' => $num]],
        'paid' => [new InvoicePaidMail($invoice), 'email.invoice.paid_subject', ['number' => $num]],
        'reminder' => [new PaymentReminderMail($invoice, 3), 'email.invoice.reminder_subject', ['number' => $num]],
    ];
}

test('invoice emails carry the subject in the customer\'s language', function (string $locale) {
    $client = Client::factory()->create(['language' => $locale]);
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'invoice_num' => 'PRO-2026/10-000002',
    ]);

    foreach (invoiceMailCases($invoice) as $name => [$mail, $key, $vars]) {
        // The trait only sets a locale when the recipient's language is not
        // the one the application already runs in, so null means English here.
        expect($mail->locale ?? 'en')->toBe($locale, "$name picks the recipient's language");
        $mail->assertHasSubject(__($key, $vars, $locale));
    }
})->with(['pl', 'en', 'de']);

test('service, domain, order and ticket emails carry the subject in the customer\'s language', function (string $locale) {
    $client = Client::factory()->create(['language' => $locale]);

    $service = Service::factory()->create(['client_id' => $client->id, 'domain' => 'example.com']);
    $domain = Domain::factory()->create(['client_id' => $client->id, 'domain' => 'example.com']);
    $order = Order::factory()->create(['client_id' => $client->id, 'order_num' => '482913']);
    $ticket = Ticket::factory()->create([
        'client_id' => $client->id,
        'department_id' => TicketDepartment::factory()->create()->id,
        'tid' => '482913',
        'title' => 'Site is down',
    ]);

    // Each subject names the thing it is about: the keys carry :domain, as the
    // hardcoded English ones carried the service or domain name.
    $named = ['domain' => 'example.com'];

    $cases = [
        'service_suspension' => [new ServiceSuspensionMail($service), 'email.service.suspension_subject', $named],
        'service_termination' => [new ServiceTerminationMail($service), 'email.service.termination_subject', $named],
        'service_unsuspension' => [new ServiceUnsuspensionMail($service), 'email.service.unsuspension_subject', $named],
        'service_welcome' => [new ServiceWelcomeMail($service), 'email.service.welcome_subject', $named],
        'cancellation' => [new CancellationConfirmMail($service), 'email.cancellation.confirm_subject', $named],
        'domain_registration' => [new DomainRegistrationMail($domain), 'email.domain.registration_subject', $named],
        'domain_renewal' => [new DomainRenewalReminderMail($domain, 30), 'email.domain.renewal_subject', $named],
        'order' => [new OrderConfirmationMail($order), 'email.order.confirmation_subject', ['number' => $order->order_num]],
    ];

    foreach ($cases as $name => [$mail, $key, $vars]) {
        $mail->assertHasSubject(__($key, $vars, $locale));
    }

    // The ticket subject is the one that is not prose: see the test below.
    (new TicketOpenedMail($ticket))->assertHasSubject('[Ticket #482913] Site is down');
})->with(['pl', 'en', 'de']);

test('the subjects are translated in every shipped language', function () {
    foreach (['en', 'pl', 'de'] as $locale) {
        foreach ([
            'email.invoice.created_subject',
            'email.invoice.overdue_subject',
            'email.invoice.paid_subject',
            'email.invoice.reminder_subject',
            'email.service.suspension_subject',
            'email.domain.registration_subject',
            'email.order.confirmation_subject',
            'email.payment_notification_rejected.subject',
        ] as $key) {
            expect(\Illuminate\Support\Facades\Lang::hasForLocale($key, $locale))->toBeTrue("{$locale}: {$key}");
        }
    }
});

/*
 * What a subject must keep, whatever language it is written in.
 *
 * Localizing them lost two things nobody noticed: the ticket subjects dropped
 * the "[Ticket #123456]" token TicketMailImportService matches on (so a
 * customer's emailed reply would have opened a new ticket instead of appending
 * to theirs) along with the ticket's own title and the "Re:" mail clients
 * thread on, and eleven others dropped the name of the thing they are about -
 * four mails titled "Service Suspended" tell a customer with four services
 * nothing.
 */
test('a ticket subject stays the token the mail importer matches, plus the title', function () {
    $client = Client::factory()->create(['language' => 'pl']);
    $department = TicketDepartment::factory()->create();
    $ticket = Ticket::factory()->create([
        'client_id' => $client->id, 'department_id' => $department->id,
        'tid' => '123456', 'title' => 'My website is down',
    ]);

    $opened = (new TicketOpenedMail($ticket))->envelope()->subject;
    $reply = (new TicketReplyMail($ticket, $ticket->replies()->make(['message' => 'any'])))->envelope()->subject;

    // The pattern the importer uses, verbatim from TicketMailImportService.
    $pattern = '/\[?\s*Ticket\s*(?:ID\s*)?[:#]?\s*#?\s*(\d{6})\s*\]?/i';

    foreach (['opened' => $opened, 'reply' => $reply] as $name => $subject) {
        expect($subject)->toContain('My website is down');
        expect(preg_match($pattern, $subject, $m))->toBe(1, "$name is recognised by the mail importer");
        expect($m[1])->toBe('123456', "$name carries the ticket id the importer reads");
    }

    expect($reply)->toStartWith('Re: ');
});

test('a subject names the service, domain or certificate it is about', function () {
    $client = Client::factory()->create(['language' => 'de']);
    $service = Service::factory()->create(['client_id' => $client->id, 'domain' => 'kunde-beispiel.de']);
    $domain = Domain::factory()->create(['client_id' => $client->id, 'domain' => 'kunde-beispiel.de']);

    $subjects = [
        'suspension' => (new ServiceSuspensionMail($service))->envelope()->subject,
        'termination' => (new ServiceTerminationMail($service))->envelope()->subject,
        'unsuspension' => (new ServiceUnsuspensionMail($service))->envelope()->subject,
        'welcome' => (new ServiceWelcomeMail($service))->envelope()->subject,
        'cancellation' => (new CancellationConfirmMail($service, 'immediate'))->envelope()->subject,
        'registration' => (new DomainRegistrationMail($domain))->envelope()->subject,
        'renewal' => (new DomainRenewalReminderMail($domain, 14))->envelope()->subject,
    ];

    foreach ($subjects as $name => $subject) {
        expect($subject)->toContain('kunde-beispiel.de');
    }
});

test('a payment reminder for an invoice already due says it is overdue', function () {
    $client = Client::factory()->create(['language' => 'en']);
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'invoice_num' => 'INV-7']);

    $upcoming = (new PaymentReminderMail($invoice, 3))->envelope()->subject;
    $late = (new PaymentReminderMail($invoice, -3))->envelope()->subject;

    expect($upcoming)->toBe(__('email.invoice.reminder_subject', ['number' => 'INV-7']))
        ->and($late)->toBe(__('email.invoice.overdue_subject', ['number' => 'INV-7']))
        ->and($late)->not->toBe($upcoming);
});
