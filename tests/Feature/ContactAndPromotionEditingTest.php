<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Promotion;
use App\Models\User;

/**
 * Two more screens that showed a control and meant nothing by it.
 *
 * The contacts page has an Edit button that is a link to "#": it goes
 * nowhere. A customer could add a contact and delete it, and correct nothing
 * in between - not a misspelt name, not a wrong address. The same table
 * printed the telephone number from a field called phone; the column is
 * phone_number, so all twenty-nine contacts on this installation showed a
 * dash where their number was.
 *
 * The promotions page has no edit control at all, so a code with the wrong
 * discount could only be deleted and made again, losing the record of how
 * often it had been used.
 */
function contactOwner(): array
{
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);

    $contact = Contact::create([
        'client_id' => $client->id,
        'first_name' => 'Accounts',
        'last_name' => 'Departmnt',
        'email' => 'accounts@example.test',
        'phone_number' => '+90 555 000 11 22',
    ]);

    return [$user, $contact];
}

test('the telephone number a contact gave is the one shown', function () {
    [$user, $contact] = contactOwner();

    $this->actingAs($user)->get(route('client.account.contacts'))
        ->assertOk()
        ->assertSee($contact->phone_number, false);
});

test('the edit button on a contact goes somewhere', function () {
    [$user, $contact] = contactOwner();

    $html = $this->actingAs($user)->get(route('client.account.contacts'))->assertOk()->getContent();

    expect($html)->toContain(route('client.account.contacts.update', $contact))
        ->and($html)->not->toContain('<a href="#" class="btn btn-outline btn-xs">');
});

test('a customer can correct a contact', function () {
    [$user, $contact] = contactOwner();

    $this->actingAs($user)->put(route('client.account.contacts.update', $contact), [
        'first_name' => 'Accounts',
        'last_name' => 'Department',
        'email' => 'accounts@example.test',
        'phone_number' => '+90 555 999 88 77',
    ])->assertRedirect();

    expect($contact->fresh()->last_name)->toBe('Department')
        ->and($contact->fresh()->phone_number)->toBe('+90 555 999 88 77');
});

test('the promotions page offers a way to change a code', function () {
    $promo = Promotion::create([
        'code' => 'WELCOME10',
        'type' => 'percentage',
        'value' => 10,
        'recurring' => false,
    ]);

    $admin = Admin::factory()->create([
        'role_id' => AdminRole::factory()->fullAdmin()->create()->id,
    ]);

    $html = $this->actingAs($admin, 'admin')
        ->get(route('admin.config.promotions'))
        ->assertOk()
        ->getContent();

    // The delete form posts to the same URL - only the method tells them
    // apart - so asking for the URL alone proves nothing.
    expect($html)->toContain(route('admin.config.promotions.update', $promo))
        ->and($html)->toContain('value="PUT"');
});

test('changing a promotion keeps its history', function () {
    $promo = Promotion::create([
        'code' => 'WELCOME10',
        'type' => 'percentage',
        'value' => 10,
        'recurring' => false,
        'uses' => 7,
    ]);

    $admin = Admin::factory()->create([
        'role_id' => AdminRole::factory()->fullAdmin()->create()->id,
    ]);

    $this->actingAs($admin, 'admin')
        ->put(route('admin.config.promotions.update', $promo), [
            'code' => 'WELCOME10',
            'type' => 'percentage',
            'value' => 15,
        ])->assertRedirect();

    expect((float) $promo->fresh()->value)->toBe(15.0)
        ->and((int) $promo->fresh()->uses)->toBe(7);
});
