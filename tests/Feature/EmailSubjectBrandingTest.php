<?php

use App\Listeners\BrandEmailSubject;
use App\Mail\InvoiceCreatedMail;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Setting;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Email;

/*
 * Every outgoing subject starts with the name of the business. Mail clients
 * sort by subject, so this groups our mail together and makes a transactional
 * message less likely to read like a stranger's. The templates keep their own
 * wording; one listener does the branding after the template has set it.
 *
 * Which name: the white-label one where the operator set it, otherwise the
 * registered name. White-label is what the shipped contract promises the
 * customer sees ("so the customer-facing site carries your brand, not ours",
 * docs/guides/themes-and-branding.md) and what company_name() honours
 * everywhere else, and an email subject is the most customer-facing text
 * there is.
 */

beforeEach(function () {
    Setting::set('CompanyLegalName', 'Example Company Sp. z o.o.');
    Setting::set('CompanyName', 'Example Company Sp. z o.o.');
    Setting::set('whitelabel_company_name', 'Example Brand');
    app()->forgetInstance('pnlcs.company_name');
});

function brandedSubject(string $subject): string
{
    $message = (new Email)->subject($subject);
    $event = new MessageSending($message);

    app(BrandEmailSubject::class)->handle($event);

    return (string) $message->getSubject();
}

it('prefixes a subject that has no brand', function () {
    expect(brandedSubject('Nowa faktura wygenerowana – #PRO-2026/11-000020'))
        ->toBe('Example Brand — Nowa faktura wygenerowana – #PRO-2026/11-000020');
});

it('moves a trailing brand to the front without duplicating it', function () {
    expect(brandedSubject('Potwierdzenie płatności - Example Brand'))
        ->toBe('Example Brand — Potwierdzenie płatności');
});

it('puts the white-label name in front of the registered one', function () {
    // The operator set a white-label name, so that is the brand the customer
    // is meant to see - even on a subject that arrives carrying the
    // registered name.
    expect(brandedSubject('Example Company Sp. z o.o. — Nowa faktura #123'))
        ->toBe('Example Brand — Nowa faktura #123');
});

it('leaves a subject that already starts with the brand alone', function () {
    expect(brandedSubject('Example Brand — Nowa faktura #123'))
        ->toBe('Example Brand — Nowa faktura #123');
});

it('keeps the ticket token so replies still import', function () {
    $subject = brandedSubject('Re: [Ticket #123456] Strona nie działa');

    expect($subject)->toBe('Example Brand — Re: [Ticket #123456] Strona nie działa')
        ->and($subject)->toContain('[Ticket #123456]');
});

it('does not double a subject that is only the brand', function () {
    expect(brandedSubject('Example Brand'))->toBe('Example Brand');
});

it('uses the registered name when no white-label name is set', function () {
    Setting::set('whitelabel_company_name', '');
    app()->forgetInstance('pnlcs.company_name');

    expect(brandedSubject('Nowa faktura #123'))
        ->toBe('Example Company Sp. z o.o. — Nowa faktura #123');
});

it('falls back to CompanyName when the legal name is empty', function () {
    Setting::set('CompanyLegalName', '');
    Setting::set('CompanyName', 'Example Hosting');
    Setting::set('whitelabel_company_name', '');
    app()->forgetInstance('pnlcs.company_name');

    expect(brandedSubject('Nowa wiadomość'))->toBe('Example Hosting — Nowa wiadomość');
});

it('brands the subject of a real invoice email', function () {
    $client = Client::factory()->create(['email' => 'brand@example.test', 'language' => 'pl']);
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'invoice_num' => 'PRO-2026/11-000020',
        'status' => 'unpaid',
    ]);
    $sent = new ArrayObject;
    Event::listen(MessageSent::class, function ($event) use ($sent) {
        $sent->append($event->message->getSubject());
    });

    Mail::to($client->email)->send(new InvoiceCreatedMail($invoice));

    expect($sent[0])->toStartWith('Example Brand — ')
        ->and($sent[0])->toContain('PRO-2026/11-000020');
});
