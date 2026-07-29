<?php

use App\Mail\InvoiceCreatedMail;
use App\Models\Admin;
use App\Models\Client;
use App\Models\EmailTemplate;
use App\Models\Invoice;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

/**
 * The last half of the templates screen.
 *
 * The subject, from address, copy-to and disable switch are applied. The
 * message box was still ignored, so an operator could rewrite the wording of
 * an email, save it, and customers carried on receiving the built-in text.
 *
 * Rewriting every email to the seeded plain text would be worse than leaving
 * it alone, so the operator's wording is used once they have actually edited
 * the template — which is what the `custom` flag is for.
 */
function bodyInvoice(): Invoice
{
    $client = Client::factory()->create([
        'first_name' => 'Ayse',
        'last_name' => 'Yilmaz',
        'email' => 'ayse@example.com',
    ]);

    return Invoice::factory()->create([
        'client_id' => $client->id,
        'invoice_num' => 'INV-BODY-1',
        'total' => 150,
        'status' => 'unpaid',
    ]);
}

function sentBody(): ArrayObject
{
    $bodies = new ArrayObject;

    Event::listen(
        MessageSent::class,
        function ($event) use ($bodies) {
            $bodies->append((string) $event->message->getHtmlBody());
        }
    );

    return $bodies;
}

test('an untouched template leaves the built-in design alone', function () {
    EmailTemplate::updateOrCreate(
        ['name' => 'Invoice Created'],
        ['type' => 'invoice', 'subject' => 'Invoice {invoice_num}', 'message' => 'Seeded plain wording.', 'custom' => false, 'disabled' => false]
    );

    $bodies = sentBody();
    Mail::to('ayse@example.com')->send(new InvoiceCreatedMail(bodyInvoice()));

    expect($bodies[0])->not->toContain('Seeded plain wording.');
});

test('the wording an operator wrote is the wording that goes out', function () {
    EmailTemplate::updateOrCreate(
        ['name' => 'Invoice Created'],
        [
            'type' => 'invoice',
            'subject' => 'Invoice {invoice_num}',
            'message' => "Merhaba {client_name},\n\n{invoice_num} numarali faturaniz hazir. Tutar: {invoice_total}.",
            'custom' => true,
            'disabled' => false,
        ]
    );

    $bodies = sentBody();
    Mail::to('ayse@example.com')->send(new InvoiceCreatedMail(bodyInvoice()));

    expect($bodies[0])->toContain('Merhaba Ayse Yilmaz')
        ->and($bodies[0])->toContain('INV-BODY-1')
        ->and($bodies[0])->toContain('150.00');
});

test('a line break in the box is a line break in the email', function () {
    EmailTemplate::updateOrCreate(
        ['name' => 'Invoice Created'],
        ['type' => 'invoice', 'subject' => 'x', 'message' => "First line\nSecond line", 'custom' => true, 'disabled' => false]
    );

    $bodies = sentBody();
    Mail::to('ayse@example.com')->send(new InvoiceCreatedMail(bodyInvoice()));

    expect($bodies[0])->toContain('First line<br');
});

test('what an operator types is not treated as markup', function () {
    EmailTemplate::updateOrCreate(
        ['name' => 'Invoice Created'],
        ['type' => 'invoice', 'subject' => 'x', 'message' => 'Ödeme için <script>alert(1)</script> tıklayın', 'custom' => true, 'disabled' => false]
    );

    $bodies = sentBody();
    Mail::to('ayse@example.com')->send(new InvoiceCreatedMail(bodyInvoice()));

    expect($bodies[0])->not->toContain('<script>');
});

test('saving a template from the admin screen marks it as the operators own', function () {
    $admin = Admin::factory()->create();
    $template = EmailTemplate::updateOrCreate(
        ['name' => 'Invoice Created'],
        ['type' => 'invoice', 'subject' => 'Invoice {invoice_num}', 'message' => 'Seeded wording.', 'custom' => false, 'disabled' => false]
    );

    $this->actingAs($admin, 'admin')
        ->put(route('admin.config.email-templates.update', $template), [
            'name' => 'Invoice Created',
            'subject' => 'Invoice {invoice_num}',
            'message' => 'Our own wording.',
        ])->assertRedirect();

    expect((bool) $template->fresh()->custom)->toBeTrue();
});
