<?php

use App\Mail\BulkMassMail;
use App\Mail\InvoiceCreatedMail;
use App\Mail\SslCertificateExpiringMail;
use App\Mail\SslCertificateIssuedMail;
use App\Models\Client;
use App\Models\EmailTemplate;
use App\Models\Invoice;
use App\Models\SslOrder;
use App\Services\EmailTemplateService;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

/**
 * Emails no template reaches, and a column nothing reads.
 *
 * The templates screen is the one place an operator can change what their
 * customers receive. Seven mailables were attached to no template at all - the
 * credit card expiry notice, the sign-in address change warning, the rejected
 * payment notification and the three SSL emails - so editing them was
 * impossible and switching them off did nothing.
 *
 * email_templates.plaintext has been on the table all along and nothing ever
 * read it: a template marked plaintext still went out as HTML.
 */
function seededTemplates(): void
{
    (new EmailTemplateSeeder)->run();
}

function capturedMessages(): ArrayObject
{
    // An object, not an array: a returned array is a copy and the listener
    // would fill in something the test never sees.
    $sent = new ArrayObject;

    Event::listen(MessageSent::class, function ($event) use ($sent) {
        $sent->append($event->message);
    });

    return $sent;
}

function invoiceForTemplate(): Invoice
{
    return Invoice::factory()->create([
        'client_id' => Client::factory()->create(['email' => 'ayse@example.test'])->id,
        'invoice_num' => 'INV-2026-91',
        'total' => 120,
        'status' => 'unpaid',
    ]);
}

it('binds every customer email an operator is offered to a template', function () {
    seededTemplates();

    $service = app(EmailTemplateService::class);
    $unbound = [];

    foreach (glob(app_path('Mail/*.php')) as $file) {
        $name = basename($file, '.php');

        // The mass mailer carries the operator's own subject and body; a
        // template would overwrite what they just wrote.
        if ($name === 'BulkMassMail') {
            continue;
        }

        if (! $service->forMailable('App\\Mail\\'.$name)) {
            $unbound[] = $name;
        }
    }

    expect($unbound)->toBe([]);
});

it('leaves the operator their own mass mail', function () {
    seededTemplates();

    expect(app(EmailTemplateService::class)->forMailable(BulkMassMail::class))->toBeNull();
});

it('names an ssl email after the domain it is about', function () {
    $vars = app(EmailTemplateService::class)->varsFor([
        'order' => new SslOrder(['domain' => 'shop.example.test']),
    ]);

    $subject = app(EmailTemplateService::class)
        ->merge('SSL Certificate Issued - {ssl_domain}', $vars);

    expect($subject)->toBe('SSL Certificate Issued - shop.example.test')
        ->and($vars)->not->toHaveKey('order_total');
});

it('still names an ssl order that has no domain yet', function () {
    $order = new SslOrder(['domain' => '']);
    $order->id = 42;

    $vars = app(EmailTemplateService::class)->varsFor(['order' => $order]);

    expect($vars['ssl_domain'])->toBe('Order #42');
});

it('lets the address-change email name both addresses', function () {
    $vars = app(EmailTemplateService::class)->varsFor([
        'previousEmail' => 'old@example.test',
        'newEmail' => 'new@example.test',
    ]);

    expect($vars['previous_email'])->toBe('old@example.test')
        ->and($vars['new_email'])->toBe('new@example.test');
});

it('does not send a subject with a field it could not fill', function () {
    EmailTemplate::updateOrCreate(
        ['name' => 'Invoice Created'],
        ['type' => 'invoice', 'subject' => 'Invoice {no_such_field} for you', 'message' => 'Body', 'disabled' => false]
    );

    $sent = capturedMessages();

    Mail::to('ayse@example.test')->send(new InvoiceCreatedMail(invoiceForTemplate()));

    expect($sent)->toHaveCount(1)
        ->and($sent[0]->getSubject())->not->toContain('{no_such_field}');
});

it('sends a plaintext template without an html part', function () {
    EmailTemplate::updateOrCreate(
        ['name' => 'Invoice Created'],
        [
            'type' => 'invoice',
            'subject' => 'Invoice #{invoice_num}',
            'message' => 'Dear {client_name}, invoice #{invoice_num} is ready.',
            'custom' => true,
            'plaintext' => true,
            'disabled' => false,
        ]
    );

    $sent = capturedMessages();

    Mail::to('ayse@example.test')->send(new InvoiceCreatedMail(invoiceForTemplate()));

    expect($sent)->toHaveCount(1)
        ->and($sent[0]->getHtmlBody())->toBeNull()
        ->and($sent[0]->getTextBody())->toContain('invoice #INV-2026-91 is ready');
});

it('still sends html for a custom template that is not plaintext', function () {
    EmailTemplate::updateOrCreate(
        ['name' => 'Invoice Created'],
        [
            'type' => 'invoice',
            'subject' => 'Invoice #{invoice_num}',
            'message' => 'Dear {client_name}, invoice #{invoice_num} is ready.',
            'custom' => true,
            'plaintext' => false,
            'disabled' => false,
        ]
    );

    $sent = capturedMessages();

    Mail::to('ayse@example.test')->send(new InvoiceCreatedMail(invoiceForTemplate()));

    expect($sent)->toHaveCount(1)
        ->and($sent[0]->getHtmlBody())->toContain('invoice #INV-2026-91 is ready');
});

it('still delivers an ssl email now that a template stands in front of it', function () {
    seededTemplates();

    $order = SslOrder::create([
        'client_id' => Client::factory()->create(['email' => 'ayse@example.test'])->id,
        'module' => 'test',
        'status' => 'Completed',
        'domain' => 'shop.example.test',
    ]);

    $sent = capturedMessages();

    Mail::to('ayse@example.test')->send(new SslCertificateIssuedMail($order));

    expect($sent)->toHaveCount(1)
        ->and($sent[0]->getSubject())->toBe('SSL Certificate Issued - shop.example.test');
});

it('counts the days in an expiring-certificate subject', function () {
    seededTemplates();

    $order = SslOrder::create([
        'client_id' => Client::factory()->create(['email' => 'ayse@example.test'])->id,
        'module' => 'test',
        'status' => 'Completed',
        'domain' => 'shop.example.test',
    ]);

    $sent = capturedMessages();

    Mail::to('ayse@example.test')->send(new SslCertificateExpiringMail($order, 14));

    expect($sent[0]->getSubject())->toBe('SSL Certificate Expiring in 14 Days - shop.example.test');
});

it('still fills the invoice subject the operator already had', function () {
    seededTemplates();

    $sent = capturedMessages();

    Mail::to('ayse@example.test')->send(new InvoiceCreatedMail(invoiceForTemplate()));

    expect($sent[0]->getSubject())->toContain('INV-2026-91');
});
