<?php

use App\Models\Client;
use App\Models\Contact;
use App\Models\User;

/**
 * A contact added by the customer receiving nothing.
 *
 * The add-contact form asks for a name, an address and a phone number. It has
 * no email-preference boxes at all - those are on the edit form, further down
 * the same page. The controller wrote all five preferences anyway, straight from
 * $request->boolean(), and a checkbox that is not on the form is a checkbox that
 * was never ticked: every contact created from the customer's own screen was
 * born receiving nothing, and the list said so in the next column.
 *
 * The table's own defaults say what was meant - all five columns default to 1 -
 * and the edit form, which does ask, proves the customer is supposed to be able
 * to choose. Only the create door decided for them, and decided no.
 */
function contactCreatingCustomer(): array
{
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);

    return [$user, $client];
}

function addContact(User $user, array $payload = [])
{
    return test()->actingAs($user)->post(route('client.account.contacts.store'), array_merge([
        'first_name' => 'Ada',
        'last_name' => 'Byron',
        'email' => 'accounts@example.test',
        'phone_number' => '555-0100',
    ], $payload));
}

it('lets a newly added contact receive the emails the form never asked about', function () {
    [$user, $client] = contactCreatingCustomer();

    addContact($user)->assertRedirect();

    $contact = Contact::where('client_id', $client->id)->firstOrFail();

    expect($contact->general_emails)->toBeTrue()
        ->and($contact->invoice_emails)->toBeTrue()
        ->and($contact->product_emails)->toBeTrue()
        ->and($contact->domain_emails)->toBeTrue()
        ->and($contact->support_emails)->toBeTrue();
});

it('honours the boxes when a form does send them', function () {
    [$user, $client] = contactCreatingCustomer();

    addContact($user, ['general_emails' => '1', 'invoice_emails' => '1'])->assertRedirect();

    $contact = Contact::where('client_id', $client->id)->firstOrFail();

    expect($contact->general_emails)->toBeTrue()
        ->and($contact->invoice_emails)->toBeTrue()
        ->and($contact->support_emails)->toBeFalse()
        ->and($contact->domain_emails)->toBeFalse();
});

it('still saves the contact the customer typed', function () {
    [$user, $client] = contactCreatingCustomer();

    addContact($user);

    $contact = Contact::where('client_id', $client->id)->firstOrFail();

    expect($contact->email)->toBe('accounts@example.test')
        ->and($contact->first_name)->toBe('Ada')
        ->and($contact->phone_number)->toBe('555-0100');
});

it('still lets the edit form switch a kind off', function () {
    [$user, $client] = contactCreatingCustomer();
    $contact = Contact::factory()->create(['client_id' => $client->id, 'invoice_emails' => true, 'general_emails' => true]);

    test()->actingAs($user)->put(route('client.account.contacts.update', $contact), [
        'first_name' => 'Ada',
        'last_name' => 'Byron',
        'email' => 'accounts@example.test',
        'general_emails' => '1',
    ])->assertRedirect();

    expect($contact->fresh()->invoice_emails)->toBeFalse()
        ->and($contact->fresh()->general_emails)->toBeTrue();
});

it('still refuses a contact with no email address', function () {
    [$user] = contactCreatingCustomer();

    addContact($user, ['email' => ''])->assertSessionHasErrors('email');
});
