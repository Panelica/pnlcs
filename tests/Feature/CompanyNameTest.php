<?php

use App\Mail\PaymentReminderMail;
use App\Models\Client;
use App\Models\EmailTemplate;
use App\Models\Invoice;
use App\Models\Setting;
use App\Services\InvoicePdfService;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

/**
 * What the business calls itself.
 *
 * Email subjects came from the template service, which read the white-label
 * name and fell back to the framework's application name — never looking at
 * the company name from Settings at all. Everything else read the company
 * name directly and never looked at the white-label name. The operator here
 * set the white-label name to one thing and left the company name as another,
 * so this morning's thirty-eight reminders went out with one name in the
 * subject line and the other in the body of the same message.
 */
function sentMessages(): ArrayObject
{
    $messages = new ArrayObject;

    Event::listen(MessageSent::class, function ($event) use ($messages) {
        $messages->append([
            'subject' => $event->message->getSubject(),
            'body' => quoted_printable_decode($event->message->getBody()->bodyToString()),
        ]);
    });

    return $messages;
}

function reminderTemplate(): void
{
    EmailTemplate::updateOrCreate(
        ['name' => 'Invoice Reminder'],
        ['type' => 'invoice', 'subject' => 'Invoice #{invoice_num} Reminder - {CompanyName}', 'message' => 'x', 'disabled' => false, 'custom' => false]
    );
}

function reminderFor(Client $client): Invoice
{
    return Invoice::factory()->create([
        'client_id' => $client->id,
        'invoice_num' => 'INV-NAME-1',
        'status' => 'unpaid',
        'total' => 20,
        'due_date' => now()->addDays(3),
    ]);
}

test('one email does not carry two names', function () {
    reminderTemplate();
    Setting::set('CompanyName', 'PNLCS Hosting', 'general');
    Setting::set('whitelabel_company_name', 'PANELICA LLC', 'general');

    $client = Client::factory()->create();
    $messages = sentMessages();

    Mail::to($client->email)->send(new PaymentReminderMail(reminderFor($client), 3));

    $sent = $messages->getArrayCopy()[0] ?? null;

    expect($sent)->not->toBeNull()
        ->and($sent['subject'])->toContain('PANELICA LLC')
        ->and($sent['body'])->toContain('PANELICA LLC')
        ->and($sent['body'])->not->toContain('PNLCS Hosting');
});

test('with no white-label name the company name is used, not the framework default', function () {
    reminderTemplate();
    Setting::set('CompanyName', 'Acme Hosting', 'general');
    Setting::set('whitelabel_company_name', '', 'general');

    $client = Client::factory()->create();
    $messages = sentMessages();

    Mail::to($client->email)->send(new PaymentReminderMail(reminderFor($client), 3));

    $sent = $messages->getArrayCopy()[0] ?? null;

    expect($sent['subject'])->toContain('Acme Hosting')
        ->and($sent['body'])->toContain('Acme Hosting');
});

test('the invoice pdf uses the same name as the emails', function () {
    Setting::set('CompanyName', 'PNLCS Hosting', 'general');
    Setting::set('whitelabel_company_name', 'PANELICA LLC', 'general');

    expect(app(InvoicePdfService::class)->companyDetails()['name'])
        ->toBe('PANELICA LLC');
});
