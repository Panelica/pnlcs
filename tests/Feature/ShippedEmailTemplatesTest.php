<?php

use App\Mail\DomainRegistrationMail;
use App\Models\Client;
use App\Models\Domain;
use App\Models\EmailTemplate;
use App\Models\Language;
use App\Translation\ShippedEmailTemplates;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

/*
 * Email templates PNLCS ships translated (database/data/email_templates).
 *
 * A language added to an install used to receive copies of the English
 * templates only, and the subject line is taken from the template row: German
 * customers' invoices went out under "New Invoice #..." although the German
 * translation of the email itself was complete.
 */

/** @return array<string, EmailTemplate> the English set, as a fresh install has it */
function englishTemplates(): array
{
    (new EmailTemplateSeeder)->run();

    return EmailTemplate::where('language', 'en')->get()->keyBy('name')->all();
}

function mergeFields(string $text): array
{
    preg_match_all('/\{[A-Za-z_][A-Za-z0-9_]*\}/', $text, $matches);
    $fields = array_values(array_unique($matches[0]));
    sort($fields);

    return $fields;
}

function shippedTemplatesMigration(): object
{
    return require database_path('migrations/2026_10_10_000001_ship_translated_email_templates.php');
}

test('every shipped template is one of ours and carries the English merge fields', function () {
    $english = englishTemplates();
    expect(ShippedEmailTemplates::locales())->toContain('de', 'pl');

    $wrong = [];
    foreach (ShippedEmailTemplates::locales() as $locale) {
        foreach (ShippedEmailTemplates::for($locale) as $name => $translated) {
            if (! isset($english[$name])) {
                $wrong[] = "{$locale} {$name}: no such template";

                continue;
            }
            foreach (['subject', 'message'] as $part) {
                $need = mergeFields($english[$name]->{$part});
                $have = mergeFields($translated[$part]);
                // Every email knows the company name; a translation may name it.
                if (array_diff($need, $have) || array_diff($have, $need, ['{CompanyName}'])) {
                    $wrong[] = "{$locale} {$name} {$part}: ".implode(' ', $have).' instead of '.implode(' ', $need);
                }
            }
        }
    }

    expect($wrong)->toBe([]);
});

test('German has every template', function () {
    expect(array_keys(ShippedEmailTemplates::for('de')))->toEqualCanonicalizing(array_keys(englishTemplates()));
});

test('a language added later starts from its shipped translation, the others from English', function () {
    $english = englishTemplates();
    $german = ShippedEmailTemplates::for('de');

    foreach (['de', 'fr'] as $code) {
        EmailTemplate::where('language', $code)->delete();
        Language::where('code', $code)->delete();
        Language::create(['code' => $code, 'name' => $code, 'native_name' => $code, 'direction' => 'ltr', 'is_active' => true]);
    }

    $de = EmailTemplate::where('language', 'de')->where('name', 'Invoice Created')->first();
    $fr = EmailTemplate::where('language', 'fr')->where('name', 'Invoice Created')->first();

    expect($de->subject)->toBe($german['Invoice Created']['subject'])
        ->and($de->message)->toBe($german['Invoice Created']['message'])
        ->and($de->custom)->toBeFalse()
        ->and($fr->subject)->toBe($english['Invoice Created']->subject);
});

test('an install with English copies gets German where nobody changed the text', function () {
    $english = englishTemplates();
    $german = ShippedEmailTemplates::for('de');
    EmailTemplate::where('language', 'de')->delete();

    $copy = fn (string $name, array $changes = []) => EmailTemplate::create(array_merge([
        'type' => $english[$name]->type, 'name' => $name, 'language' => 'de',
        'subject' => $english[$name]->subject, 'message' => $english[$name]->message, 'custom' => false,
    ], $changes));

    $untouched = $copy('Invoice Created');
    $ownSubject = $copy('Invoice Overdue', ['subject' => 'Bitte zahlen: #{invoice_num}']);
    $ownTemplate = $copy('Order Confirmation', ['custom' => true]);

    shippedTemplatesMigration()->up();
    shippedTemplatesMigration()->up();

    expect($untouched->fresh()->only('subject', 'message'))->toBe($german['Invoice Created'])
        ->and($ownSubject->fresh()->subject)->toBe('Bitte zahlen: #{invoice_num}')
        ->and($ownSubject->fresh()->message)->toBe($german['Invoice Overdue']['message'])
        ->and($ownTemplate->fresh()->subject)->toBe($english['Order Confirmation']->subject);

    shippedTemplatesMigration()->down();

    expect($untouched->fresh()->subject)->toBe($english['Invoice Created']->subject)
        ->and($untouched->fresh()->message)->toBe($english['Invoice Created']->message)
        ->and($ownSubject->fresh()->subject)->toBe('Bitte zahlen: #{invoice_num}')
        ->and($ownSubject->fresh()->message)->toBe($english['Invoice Overdue']->message);
});

test('a German customer gets a German subject line', function () {
    englishTemplates();
    EmailTemplate::where('language', 'de')->delete();
    Language::where('code', 'de')->delete();
    Language::create(['code' => 'de', 'name' => 'German', 'native_name' => 'Deutsch', 'direction' => 'ltr', 'is_active' => true]);

    $client = Client::factory()->create(['language' => 'de']);
    $domain = Domain::factory()->create(['client_id' => $client->id, 'domain' => 'example-de.com']);

    $subjects = new ArrayObject;
    Event::listen(MessageSent::class, fn ($event) => $subjects->append($event->message->getSubject()));
    Mail::to($client->email)->send(new DomainRegistrationMail($domain));

    expect($subjects->getArrayCopy()[0] ?? '')->toStartWith('Domain example-de.com registriert');
});
