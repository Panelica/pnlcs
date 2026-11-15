<?php

use App\Listeners\ApplyEmailTemplate;
use App\Mail\InvoiceCreatedMail;
use App\Mail\PasswordResetMail;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\EmailTemplate;
use App\Models\Invoice;
use App\Models\Setting;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

/*
 * Keeping a copy of what the shop sends (community.pnlcs.com/t/31).
 *
 * A template's copy-to address was honoured when mail was sent, but no screen
 * could set it. It is a field on the template now, and General Settings has
 * one address list that every email is blind-copied to. What carries a
 * password, a sign-in link or a code is never copied, by either.
 */

function ectInvoice(string $email = 'ect-customer@example.com'): Invoice
{
    $client = Client::factory()->create(['email' => $email]);

    return Invoice::factory()->create(['client_id' => $client->id, 'invoice_num' => 'INV-ECT-1', 'total' => 10, 'status' => 'unpaid']);
}

function ectTemplate(array $attributes = []): EmailTemplate
{
    return EmailTemplate::updateOrCreate(['name' => 'Invoice Created'], array_merge([
        'type' => 'invoice', 'subject' => 'New Invoice #{invoice_num}', 'message' => 'Dear {client_name}.', 'disabled' => false, 'copy_to' => null,
    ], $attributes));
}

/** @return ArrayObject<int, string> the blind-copy addresses of everything sent */
function ectBcc(): ArrayObject
{
    $bcc = new ArrayObject;
    Event::listen(MessageSent::class, function ($event) use ($bcc) {
        foreach ($event->message->getBcc() as $address) {
            $bcc->append($address->getAddress());
        }
    });

    return $bcc;
}

function ectAdmin(): Admin
{
    return Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);
}

test('with nothing set no email is copied anywhere', function () {
    ectTemplate();
    $bcc = ectBcc();

    Mail::to('ect-customer@example.com')->send(new InvoiceCreatedMail(ectInvoice()));

    expect($bcc->getArrayCopy())->toBe([]);
});

test('every email is blind-copied to the addresses in General Settings', function () {
    Setting::set(ApplyEmailTemplate::COPY_SETTING, 'archive@example.com; second@example.com');
    ectTemplate();
    $bcc = ectBcc();

    Mail::to('ect-customer@example.com')->send(new InvoiceCreatedMail(ectInvoice()));

    expect($bcc->getArrayCopy())->toBe(['archive@example.com', 'second@example.com']);
});

test('an email without a template of its own is copied too', function () {
    Setting::set(ApplyEmailTemplate::COPY_SETTING, 'archive@example.com');
    EmailTemplate::where('name', 'Invoice Created')->delete();
    $bcc = ectBcc();

    Mail::to('ect-customer@example.com')->send(new InvoiceCreatedMail(ectInvoice()));

    expect($bcc->getArrayCopy())->toBe(['archive@example.com']);
});

test('the shop-wide copy and the template copy are both sent, an address in both only once', function () {
    Setting::set(ApplyEmailTemplate::COPY_SETTING, 'archive@example.com');
    ectTemplate(['copy_to' => 'accounts@example.com, archive@example.com']);
    $bcc = ectBcc();

    Mail::to('ect-customer@example.com')->send(new InvoiceCreatedMail(ectInvoice()));

    expect($bcc->getArrayCopy())->toBe(['archive@example.com', 'accounts@example.com']);
});

test('an email already going to the copy address is not sent there twice', function () {
    Setting::set(ApplyEmailTemplate::COPY_SETTING, 'Billing@Example.com');
    ectTemplate();
    $bcc = ectBcc();

    Mail::to('billing@example.com')->send(new InvoiceCreatedMail(ectInvoice('billing@example.com')));

    expect($bcc->getArrayCopy())->toBe([]);
});

test('a password reset link is never copied, whatever is set', function () {
    Setting::set(ApplyEmailTemplate::COPY_SETTING, 'archive@example.com');
    EmailTemplate::updateOrCreate(['name' => 'Password Reset Confirmation'], [
        'type' => 'general', 'subject' => 'Reset', 'message' => 'x', 'disabled' => false, 'custom' => false, 'copy_to' => 'accounts@example.com',
    ]);
    $bcc = ectBcc();

    Mail::to('ect-customer@example.com')->send(new PasswordResetMail('https://example.com/reset/token', 'Ayse'));

    expect($bcc->getArrayCopy())->toBe([]);
});

test('the shop-wide copy address is saved from General Settings, and a typo is refused', function () {
    $admin = ectAdmin();

    $this->actingAs($admin, 'admin')->get(route('admin.settings.general'))->assertOk()->assertSee(__('admin.settings.mail_copy_to'));

    $this->actingAs($admin, 'admin')->post(route('admin.settings.general.update'), ['MailCopyTo' => 'archive@example.com, second@example.com'])
        ->assertSessionHasNoErrors();
    expect(Setting::get(ApplyEmailTemplate::COPY_SETTING))->toBe('archive@example.com, second@example.com');

    $this->actingAs($admin, 'admin')->post(route('admin.settings.general.update'), ['MailCopyTo' => 'archive@example.com, not-an-address'])
        ->assertSessionHasErrors('MailCopyTo');
    expect(Setting::get(ApplyEmailTemplate::COPY_SETTING))->toBe('archive@example.com, second@example.com');

    $this->actingAs($admin, 'admin')->post(route('admin.settings.general.update'), ['MailCopyTo' => ''])->assertSessionHasNoErrors();
    expect((string) Setting::get(ApplyEmailTemplate::COPY_SETTING))->toBe('');
});

test('a template\'s copy address is set on the template, and a typo is refused', function () {
    $admin = ectAdmin();
    $template = ectTemplate();

    $this->actingAs($admin, 'admin')->get(route('admin.config.email-templates'))->assertOk()->assertSee(__('admin.email_templates.copy_to'));

    $this->actingAs($admin, 'admin')->put(route('admin.config.email-templates.update', $template), ['copy_to' => 'accounts@example.com'])
        ->assertSessionHasNoErrors();
    expect($template->fresh()->copy_to)->toBe('accounts@example.com');

    $this->actingAs($admin, 'admin')->put(route('admin.config.email-templates.update', $template), ['copy_to' => 'accounts@'])
        ->assertSessionHasErrors('copy_to');
    expect($template->fresh()->copy_to)->toBe('accounts@example.com');

    // Saving the form with the field emptied takes the copy off again.
    $this->actingAs($admin, 'admin')->put(route('admin.config.email-templates.update', $template), ['copy_to' => ''])->assertSessionHasNoErrors();
    expect($template->fresh()->copy_to)->toBeNull();
});
