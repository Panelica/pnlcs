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
        expect($mail->locale)->toBe($locale, "$name picks the recipient's language");
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

    $cases = [
        'service_suspension' => [new ServiceSuspensionMail($service), 'email.service.suspension_subject', []],
        'service_termination' => [new ServiceTerminationMail($service), 'email.service.termination_subject', []],
        'service_unsuspension' => [new ServiceUnsuspensionMail($service), 'email.service.unsuspension_subject', []],
        'service_welcome' => [new ServiceWelcomeMail($service), 'email.service.welcome_subject', []],
        'cancellation' => [new CancellationConfirmMail($service), 'email.cancellation.confirm_subject', []],
        'domain_registration' => [new DomainRegistrationMail($domain), 'email.domain.registration_subject', []],
        'domain_renewal' => [new DomainRenewalReminderMail($domain, 30), 'email.domain.renewal_subject', []],
        'order' => [new OrderConfirmationMail($order), 'email.order.confirmation_subject', ['number' => $order->order_num]],
        'ticket' => [new TicketOpenedMail($ticket), 'email.ticket.opened_subject', ['number' => '482913']],
    ];

    foreach ($cases as $name => [$mail, $key, $vars]) {
        $mail->assertHasSubject(__($key, $vars, $locale));
    }
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
            'email.ticket.opened_subject',
            'email.payment_notification_rejected.subject',
        ] as $key) {
            expect(\Illuminate\Support\Facades\Lang::hasForLocale($key, $locale))->toBeTrue("{$locale}: {$key}");
        }
    }
});
