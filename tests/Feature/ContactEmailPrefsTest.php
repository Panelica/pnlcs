<?php

use App\Mail\InvoiceCreatedMail;
use App\Mail\PasswordResetMail;
use App\Models\Client;
use App\Models\Contact;
use App\Models\EmailTemplate;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

/**
 * Contacts and the emails they asked for.
 *
 * A client can add contacts — an accounts department, a technical colleague —
 * and the table has a flag for each kind of email they should receive. Nothing
 * read them, nothing set them, and the screen showed a Permissions column that
 * meant nothing next to an Edit button that went nowhere. Everything went to
 * the account's own address and no further.
 */
function contactClient(): array
{
    $user = User::factory()->create();
    $client = Client::factory()->create(['email' => 'owner@example.com']);
    $user->clients()->attach($client->id);

    return [$user, $client];
}

function invoiceTemplateFor(): EmailTemplate
{
    return EmailTemplate::updateOrCreate(
        ['name' => 'Invoice Created'],
        ['type' => 'invoice', 'subject' => 'Invoice {invoice_num}', 'message' => 'x', 'disabled' => false, 'custom' => false]
    );
}

function recipientsOf(): ArrayObject
{
    $seen = new ArrayObject;

    Event::listen(
        MessageSent::class,
        function ($event) use ($seen) {
            foreach ($event->message->getCc() as $address) {
                $seen->append($address->getAddress());
            }
        }
    );

    return $seen;
}

test('a contact who asked for invoices gets a copy', function () {
    invoiceTemplateFor();
    [, $client] = contactClient();

    Contact::create([
        'client_id' => $client->id,
        'first_name' => 'Accounts',
        'last_name' => 'Department',
        'email' => 'accounts@example.com',
        'invoice_emails' => true,
    ]);

    $cc = recipientsOf();
    Mail::to($client->email)->send(new InvoiceCreatedMail(Invoice::factory()->create([
        'client_id' => $client->id, 'invoice_num' => 'INV-CC-1', 'total' => 10, 'status' => 'unpaid',
    ])));

    expect($cc->getArrayCopy())->toContain('accounts@example.com');
});

test('a contact who did not ask for invoices is left out of it', function () {
    invoiceTemplateFor();
    [, $client] = contactClient();

    Contact::create([
        'client_id' => $client->id,
        'first_name' => 'Technical',
        'last_name' => 'Colleague',
        'email' => 'tech@example.com',
        'invoice_emails' => false,
        'support_emails' => true,
    ]);

    $cc = recipientsOf();
    Mail::to($client->email)->send(new InvoiceCreatedMail(Invoice::factory()->create([
        'client_id' => $client->id, 'invoice_num' => 'INV-CC-2', 'total' => 10, 'status' => 'unpaid',
    ])));

    expect($cc->getArrayCopy())->not->toContain('tech@example.com');
});

test('a client with no contacts is unaffected', function () {
    invoiceTemplateFor();
    [, $client] = contactClient();

    $cc = recipientsOf();
    Mail::to($client->email)->send(new InvoiceCreatedMail(Invoice::factory()->create([
        'client_id' => $client->id, 'invoice_num' => 'INV-CC-3', 'total' => 10, 'status' => 'unpaid',
    ])));

    expect($cc->getArrayCopy())->toBe([]);
});

test('a customer can say which emails a contact receives', function () {
    [$user, $client] = contactClient();

    $this->actingAs($user)->post(route('client.account.contacts.store'), [
        'first_name' => 'Accounts',
        'last_name' => 'Department',
        'email' => 'accounts@example.com',
        'invoice_emails' => '1',
        'support_emails' => '1',
    ])->assertRedirect();

    $contact = Contact::where('email', 'accounts@example.com')->firstOrFail();

    expect((bool) $contact->invoice_emails)->toBeTrue()
        ->and((bool) $contact->support_emails)->toBeTrue()
        ->and((bool) $contact->domain_emails)->toBeFalse();
});

test('a customer can change them afterwards', function () {
    [$user, $client] = contactClient();

    $contact = Contact::create([
        'client_id' => $client->id,
        'first_name' => 'Accounts',
        'last_name' => 'Department',
        'email' => 'accounts@example.com',
        'invoice_emails' => false,
    ]);

    $this->actingAs($user)->put(route('client.account.contacts.update', $contact), [
        'first_name' => 'Accounts',
        'last_name' => 'Department',
        'email' => 'accounts@example.com',
        'invoice_emails' => '1',
    ])->assertRedirect();

    expect((bool) $contact->fresh()->invoice_emails)->toBeTrue();
});

test('a customer cannot change somebody elses contact', function () {
    [$user] = contactClient();
    $stranger = Client::factory()->create();

    $contact = Contact::create([
        'client_id' => $stranger->id,
        'first_name' => 'Not',
        'last_name' => 'Yours',
        'email' => 'stranger@example.com',
    ]);

    $this->actingAs($user)->put(route('client.account.contacts.update', $contact), [
        'first_name' => 'Hijacked',
        'last_name' => 'Name',
        'email' => 'stranger@example.com',
    ])->assertForbidden();

    expect($contact->fresh()->first_name)->toBe('Not');
});

test('a password reset is never copied to anyone', function () {
    // Every live contact is flagged for general emails, and password resets
    // are a general template - so copying contacts in would have mailed the
    // reset link to everyone on the account.
    EmailTemplate::updateOrCreate(
        ['name' => 'Password Reset Confirmation'],
        ['type' => 'general', 'subject' => 'Reset', 'message' => 'x', 'disabled' => false, 'custom' => false, 'copy_to' => 'archive@example.com']
    );

    [, $client] = contactClient();

    Contact::create([
        'client_id' => $client->id,
        'first_name' => 'Nosy',
        'last_name' => 'Colleague',
        'email' => 'colleague@example.com',
        'general_emails' => true,
    ]);

    $copied = new ArrayObject;
    Event::listen(
        MessageSent::class,
        function ($event) use ($copied) {
            foreach (array_merge($event->message->getCc(), $event->message->getBcc()) as $address) {
                $copied->append($address->getAddress());
            }
        }
    );

    Mail::to($client->email)->send(new PasswordResetMail('https://example.com/reset/abc', $client->email));

    expect($copied->getArrayCopy())->toBe([]);
});
