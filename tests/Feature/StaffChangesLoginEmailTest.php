<?php

use App\Mail\EmailVerificationMail;
use App\Mail\LoginEmailChangedMail;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\ApiCredential;
use App\Models\Client;
use App\Models\User;
use Database\Factories\ApiCredentialFactory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/*
 * Staff changing an account's email address (#210, the admin half).
 *
 * The client record and the login are separate rows. Staff changed the
 * client's address on the admin form and the customer still signed in with the
 * old one, and saw the old one on their profile; no screen could change the
 * login. A login of the account that signed in with the old address now moves
 * with it, the new address is asked to confirm itself and the old one is told,
 * as when the customer changes it themselves.
 */

function staffLoginAdmin(): Admin
{
    return Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);
}

/** @return array{0: Client, 1: User} an account its owner signs in to with the account's address */
function staffLoginAccount(string $email = 'typo@example.test'): array
{
    $client = Client::factory()->create(['email' => $email, 'first_name' => 'Ada', 'last_name' => 'Owner', 'country' => 'GB']);
    $user = User::factory()->create(['email' => $email, 'password' => Hash::make('Secret-Passw0rd'), 'email_verified_at' => now()]);
    $user->clients()->attach($client->id, ['owner' => true]);

    return [$client, $user];
}

function staffLoginForm(Client $client, array $over = []): array
{
    return array_merge([
        'first_name' => $client->first_name, 'last_name' => $client->last_name, 'email' => $client->email,
        'country' => $client->country ?: 'GB', 'status' => 'active',
    ], $over);
}

test('staff change the address on the client form, and the customer signs in with the new one', function () {
    Mail::fake();
    [$client, $user] = staffLoginAccount();

    $this->actingAs(staffLoginAdmin(), 'admin')
        ->put(route('admin.clients.update', $client), staffLoginForm($client, ['email' => 'right@example.test']))
        ->assertRedirect(route('admin.clients.show', $client))
        ->assertSessionHas('success', __('admin.clients.updated_login_moved', ['email' => 'right@example.test']));

    expect($client->fresh()->email)->toBe('right@example.test')
        ->and($user->fresh()->email)->toBe('right@example.test')
        ->and($user->fresh()->email_verified_at)->toBeNull();
    Mail::assertSent(EmailVerificationMail::class, fn ($m) => $m->hasTo('right@example.test'));
    Mail::assertQueued(LoginEmailChangedMail::class, fn ($m) => $m->hasTo('typo@example.test') && $m->byStaff && $m->newEmail === 'right@example.test');

    // A new request as the customer: actingAs() above left the admin guard as
    // the default one.
    auth()->guard('admin')->logout();
    auth()->shouldUse('web');
    $this->post(route('client.login.submit'), ['email' => 'right@example.test', 'password' => 'Secret-Passw0rd']);
    $this->assertAuthenticatedAs($user->fresh());
});

test('a login on another address is somebody else and stays as it is', function () {
    Mail::fake();
    [$client, $owner] = staffLoginAccount();
    $invited = User::factory()->create(['email' => 'colleague@example.test']);
    $invited->clients()->attach($client->id, ['owner' => false]);

    $this->actingAs(staffLoginAdmin(), 'admin')
        ->put(route('admin.clients.update', $client), staffLoginForm($client, ['email' => 'right@example.test']))
        ->assertRedirect();

    expect($owner->fresh()->email)->toBe('right@example.test')
        ->and($invited->fresh()->email)->toBe('colleague@example.test');
});

test('an account whose owner signs in with a different address keeps that login', function () {
    // Imported from WHMCS 8, or set apart on purpose: the login is not the
    // account's address, so it is not the one being changed.
    Mail::fake();
    $client = Client::factory()->create(['email' => 'billing@example.test', 'country' => 'GB']);
    $user = User::factory()->create(['email' => 'person@example.test']);
    $user->clients()->attach($client->id, ['owner' => true]);

    $this->actingAs(staffLoginAdmin(), 'admin')
        ->put(route('admin.clients.update', $client), staffLoginForm($client, ['email' => 'accounts@example.test']))
        ->assertSessionHas('success', __('messages.success.client_updated'));

    expect($client->fresh()->email)->toBe('accounts@example.test')
        ->and($user->fresh()->email)->toBe('person@example.test');
    Mail::assertNothingQueued();
});

test('an address another customer signs in with is refused, and nothing is changed', function () {
    Mail::fake();
    [$client, $user] = staffLoginAccount();
    User::factory()->create(['email' => 'right@example.test']);

    $this->actingAs(staffLoginAdmin(), 'admin')
        ->from(route('admin.clients.edit', $client))
        ->put(route('admin.clients.update', $client), staffLoginForm($client, ['email' => 'right@example.test', 'first_name' => 'Changed']))
        ->assertRedirect(route('admin.clients.edit', $client))
        ->assertSessionHasErrors(['email' => __('admin.clients.login_email_taken')]);

    expect($client->fresh()->email)->toBe('typo@example.test')
        ->and($client->fresh()->first_name)->toBe('Ada')
        ->and($user->fresh()->email)->toBe('typo@example.test');
    Mail::assertNothingSent();
    Mail::assertNothingQueued();
});

test('a password for an account without a login opens it on the new address', function () {
    Mail::fake();
    $client = Client::factory()->create(['email' => 'old@example.test', 'country' => 'GB']);

    $this->actingAs(staffLoginAdmin(), 'admin')
        ->put(route('admin.clients.update', $client), staffLoginForm($client, [
            'email' => 'new@example.test', 'password' => 'Opened-Passw0rd', 'password_confirmation' => 'Opened-Passw0rd',
        ]))->assertRedirect();

    expect($client->fresh()->users()->pluck('email')->all())->toBe(['new@example.test']);
});

test('changing only the name leaves the login and sends nothing', function () {
    Mail::fake();
    [$client, $user] = staffLoginAccount();

    $this->actingAs(staffLoginAdmin(), 'admin')
        ->put(route('admin.clients.update', $client), staffLoginForm($client, ['first_name' => 'Renamed']))
        ->assertSessionHas('success', __('messages.success.client_updated'));

    expect($user->fresh()->email)->toBe('typo@example.test')
        ->and($user->fresh()->email_verified_at)->not->toBeNull();
    Mail::assertNothingSent();
    Mail::assertNothingQueued();
});

test('the warning to the old address says staff made the change, not a password', function () {
    $html = (new LoginEmailChangedMail('typo@example.test', 'right@example.test', byStaff: true))->render();

    expect($html)->toContain(e(__('email.login_email_changed.how_staff')))
        ->and($html)->toContain(e(__('email.login_email_changed.not_you_staff')))
        ->and($html)->not->toContain(e(__('email.login_email_changed.how')));

    $own = (new LoginEmailChangedMail('typo@example.test', 'right@example.test'))->render();
    expect($own)->toContain(e(__('email.login_email_changed.how')))
        ->and($own)->not->toContain(e(__('email.login_email_changed.how_staff')));
});

function staffLoginApi(): array
{
    return ['X-API-Key' => ApiCredential::factory()->create()->identifier, 'X-API-Secret' => ApiCredentialFactory::PLAINTEXT_SECRET];
}

test('the api moves the login with the account address too, and refuses a taken one', function () {
    Mail::fake();
    [$client, $user] = staffLoginAccount();

    $this->withHeaders(staffLoginApi())->postJson('/api/v1/updateclient', ['clientid' => $client->id, 'email' => 'right@example.test'])
        ->assertSuccessful();

    expect($user->fresh()->email)->toBe('right@example.test');
    Mail::assertQueued(LoginEmailChangedMail::class, fn ($m) => $m->hasTo('typo@example.test') && $m->byStaff);

    User::factory()->create(['email' => 'taken@example.test']);
    $this->withHeaders(staffLoginApi())->postJson('/api/v1/updateclient', ['clientid' => $client->id, 'email' => 'taken@example.test'])
        ->assertStatus(422);
    expect($client->fresh()->email)->toBe('right@example.test')
        ->and($user->fresh()->email)->toBe('right@example.test');
});

test('the api changing a login directly asks the new address to confirm and tells the old one', function () {
    Mail::fake();
    [, $user] = staffLoginAccount();

    $this->withHeaders(staffLoginApi())->postJson('/api/v1/updateuser', ['userid' => $user->id, 'email' => 'right@example.test'])
        ->assertSuccessful();

    expect($user->fresh()->email)->toBe('right@example.test')
        ->and($user->fresh()->email_verified_at)->toBeNull();
    Mail::assertSent(EmailVerificationMail::class, fn ($m) => $m->hasTo('right@example.test'));
    Mail::assertQueued(LoginEmailChangedMail::class, fn ($m) => $m->hasTo('typo@example.test') && $m->byStaff);
});
