<?php

use App\Models\Admin;
use App\Models\BannedEmail;
use App\Models\Client;
use App\Models\User;
use Illuminate\Testing\TestResponse;

/**
 * Banning an address.
 *
 * The screen says "Ban Email / Domain" and the list was read in exactly one
 * place: the fraud score on an order. Nothing stopped a banned address opening
 * an account in the first place, so someone banned for chargebacks could sign
 * up again in a minute and be back inside the panel.
 */
function signup(string $email): TestResponse
{
    return test()->post(route('client.register.submit'), [
        'first_name' => 'New',
        'last_name' => 'Customer',
        'email' => $email,
        'password' => 'Secret123!',
        'password_confirmation' => 'Secret123!',
        'tos' => '1',
    ]);
}

test('a banned address cannot open an account', function () {
    BannedEmail::create(['domain' => 'crook@spam.test', 'reason' => 'chargebacks']);

    signup('crook@spam.test')->assertSessionHasErrors('email');

    expect(User::where('email', 'crook@spam.test')->exists())->toBeFalse()
        ->and(Client::where('email', 'crook@spam.test')->exists())->toBeFalse();
});

test('a banned domain covers every address on it', function () {
    BannedEmail::create(['domain' => 'spam.test', 'reason' => 'throwaway domain']);

    signup('someone.else@spam.test')->assertSessionHasErrors('email');

    expect(User::where('email', 'someone.else@spam.test')->exists())->toBeFalse();
});

test('the ban is not case sensitive', function () {
    BannedEmail::create(['domain' => 'Spam.Test', 'reason' => 'throwaway domain']);

    signup('MixedCase@SPAM.test')->assertSessionHasErrors('email');

    expect(User::count())->toBe(0);
});

test('an ordinary address signs up as before', function () {
    BannedEmail::create(['domain' => 'spam.test', 'reason' => 'throwaway domain']);

    signup('real.customer@example.com')->assertRedirect();

    expect(User::where('email', 'real.customer@example.com')->exists())->toBeTrue()
        ->and(Client::where('email', 'real.customer@example.com')->exists())->toBeTrue();
});

test('an operator can still add a banned address by hand', function () {
    BannedEmail::create(['domain' => 'spam.test', 'reason' => 'throwaway domain']);
    $admin = Admin::factory()->create();

    // Deliberate override: the ban stops strangers signing themselves up, not
    // an operator who has decided otherwise.
    $this->actingAs($admin, 'admin')->post(route('admin.clients.store'), [
        'first_name' => 'Second',
        'last_name' => 'Chance',
        'email' => 'forgiven@spam.test',
        'status' => 'active',
    ])->assertRedirect();

    expect(Client::where('email', 'forgiven@spam.test')->exists())->toBeTrue();
});
