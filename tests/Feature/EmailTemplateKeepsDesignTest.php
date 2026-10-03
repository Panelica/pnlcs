<?php

use App\Mail\InvoiceCreatedMail;
use App\Models\Admin;
use App\Models\Client;
use App\Models\EmailTemplate;
use App\Models\Invoice;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

/*
 * Saving a template marked it custom whatever was changed. The form sends the
 * stored (seeded) text back with every save, so changing a subject or switching
 * a template off swapped the designed email for that plain text. And a body the
 * operator did write went out as bare text, with none of the design around it.
 */

function kdInvoice(): Invoice
{
    return Invoice::factory()->create(['client_id' => Client::factory()->create(['email' => 'kd@example.test'])->id, 'invoice_num' => 'INV-KD-1', 'total' => 80, 'status' => 'unpaid']);
}

function kdHtml(): ArrayObject
{
    $bodies = new ArrayObject;
    Event::listen(MessageSent::class, fn ($event) => $bodies->append((string) $event->message->getHtmlBody()));

    return $bodies;
}

function kdTemplate(): EmailTemplate
{
    return EmailTemplate::updateOrCreate(['name' => 'Invoice Created'],
        ['type' => 'invoice', 'subject' => 'Invoice {invoice_num}', 'message' => "Seeded plain wording.\nSecond line.", 'custom' => false, 'disabled' => false]);
}

function kdSave(EmailTemplate $template, array $changes)
{
    $form = array_merge(['name' => $template->name, 'subject' => $template->subject, 'type' => $template->type,
        'message' => str_replace("\n", "\r\n", $template->message), 'disabled' => 0], $changes);

    return test()->actingAs(Admin::factory()->create(), 'admin')->put(route('admin.config.email-templates.update', $template), $form);
}

test('changing only the subject or the switch keeps the designed email', function () {
    $template = kdTemplate();

    kdSave($template, ['subject' => 'Your invoice {invoice_num}'])->assertRedirect();

    expect($template->fresh())->custom->toBeFalse()->subject->toBe('Your invoice {invoice_num}');
    $html = kdHtml();
    Mail::to('kd@example.test')->send(new InvoiceCreatedMail(kdInvoice()));
    expect($html[0])->not->toContain('Seeded plain wording.');
});

test('a body the operator rewrote goes out inside the shared frame', function () {
    $template = kdTemplate();

    kdSave($template, ['message' => "Hello {client_name},\n\nYour invoice {invoice_num} is ready."])->assertRedirect();
    expect($template->fresh()->custom)->toBeTrue();

    $html = kdHtml();
    Mail::to('kd@example.test')->send(new InvoiceCreatedMail(kdInvoice()));
    expect($html[0])->toContain('<h2 style="color:#405189;">'.company_name().'</h2>')
        ->toContain('Your invoice INV-KD-1 is ready.')
        ->toContain('<p style="line-height:1.6;">');
});

test('the operator can go back to the built-in email', function () {
    $template = kdTemplate();
    $template->update(['custom' => true, 'message' => 'Mine.']);

    test()->actingAs(Admin::factory()->create(), 'admin')->post(route('admin.config.email-templates.reset', $template))->assertRedirect();

    expect($template->fresh())->custom->toBeFalse()->message->toBe('Mine.');
    test()->actingAs(Admin::factory()->create(), 'admin')->get(route('admin.config.email-templates'))
        ->assertOk()->assertSee(__('admin.email_templates.source_builtin'));
});
