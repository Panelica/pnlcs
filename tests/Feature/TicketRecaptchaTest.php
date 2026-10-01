<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use App\Models\User;
use App\Services\RecaptchaService;
use Illuminate\Support\Facades\Http;

/**
 * Google reCAPTCHA on the signed-in ticket form.
 *
 * Accounts can be made in bulk, so the form behind the login is a way in for
 * spam tickets as well as the public contact form. It has its own switch:
 * an operator may want one form guarded and not the other. The keys are the
 * ones the contact form uses.
 */
function recaptchaTicketOpener(): array
{
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);

    $department = TicketDepartment::create(['name' => 'Support', 'hidden' => false, 'sort_order' => 1]);

    return [$user, $client, $department];
}

function recaptchaKeys(): void
{
    Setting::set('RecaptchaSiteKey', 'site-key-for-tests');
    Setting::set('RecaptchaSecretKey', 'secret-key-for-tests');
}

function postRecaptchaTicket(User $user, TicketDepartment $department, array $payload = [])
{
    return test()->actingAs($user)->post(route('client.tickets.store'), array_merge([
        'department_id' => $department->id,
        'subject' => 'My site is down',
        'message' => 'Since this morning.',
    ], $payload));
}

it('leaves the ticket form as it was while its switch is off', function () {
    Http::fake();
    recaptchaKeys();
    [$user, $client, $department] = recaptchaTicketOpener();

    test()->actingAs($user)->get(route('client.tickets.create'))->assertOk()->assertDontSee('g-recaptcha', false);
    postRecaptchaTicket($user, $department)->assertSessionHasNoErrors();

    expect(Ticket::where('client_id', $client->id)->count())->toBe(1);
    Http::assertNothingSent();
});

it('keeps the two switches apart', function () {
    Http::fake();
    recaptchaKeys();
    Setting::set('RecaptchaEnabled', '1');
    [$user, $client, $department] = recaptchaTicketOpener();

    expect(app(RecaptchaService::class)->enabled('contact'))->toBeTrue()
        ->and(app(RecaptchaService::class)->enabled('tickets'))->toBeFalse();

    test()->actingAs($user)->get(route('client.tickets.create'))->assertOk()->assertDontSee('g-recaptcha', false);
    postRecaptchaTicket($user, $department)->assertSessionHasNoErrors();

    expect(Ticket::where('client_id', $client->id)->count())->toBe(1);
});

it('does not guard the contact form when only the ticket switch is on', function () {
    Http::fake();
    recaptchaKeys();
    Setting::set('RecaptchaTicketsEnabled', '1');
    [, , $department] = recaptchaTicketOpener();

    test()->get(route('client.contact'))->assertOk()->assertDontSee('g-recaptcha', false);
    test()->post(route('client.contact.submit'), [
        'name' => 'Visitor',
        'email' => 'visitor@example.test',
        'department_id' => $department->id,
        'subject' => 'A question',
        'message' => 'Please get back to me.',
    ])->assertSessionHasNoErrors();

    expect(Ticket::whereNull('client_id')->count())->toBe(1);
});

it('counts the ticket switch with a key missing as off', function () {
    Http::fake();
    Setting::set('RecaptchaTicketsEnabled', '1');
    Setting::set('RecaptchaSiteKey', 'site-key-for-tests');
    [$user, $client, $department] = recaptchaTicketOpener();

    expect(app(RecaptchaService::class)->enabled('tickets'))->toBeFalse();
    postRecaptchaTicket($user, $department)->assertSessionHasNoErrors();

    expect(Ticket::where('client_id', $client->id)->count())->toBe(1);
});

it('shows the challenge on the ticket form once switched on', function () {
    recaptchaKeys();
    Setting::set('RecaptchaTicketsEnabled', '1');
    [$user] = recaptchaTicketOpener();

    test()->actingAs($user)->get(route('client.tickets.create'))
        ->assertOk()
        ->assertSee('data-sitekey="site-key-for-tests"', false)
        ->assertSee('https://www.google.com/recaptcha/api.js', false)
        ->assertDontSee('secret-key-for-tests');
});

it('opens no ticket when the challenge was not answered', function () {
    Http::fake();
    recaptchaKeys();
    Setting::set('RecaptchaTicketsEnabled', '1');
    [$user, $client, $department] = recaptchaTicketOpener();

    postRecaptchaTicket($user, $department)->assertSessionHasErrors(RecaptchaService::FIELD);

    expect(Ticket::where('client_id', $client->id)->count())->toBe(0);
    Http::assertNothingSent();
});

it('opens no ticket when Google refuses the answer', function () {
    Http::fake([RecaptchaService::ENDPOINT => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']])]);
    recaptchaKeys();
    Setting::set('RecaptchaTicketsEnabled', '1');
    [$user, $client, $department] = recaptchaTicketOpener();

    postRecaptchaTicket($user, $department, [RecaptchaService::FIELD => 'forged'])
        ->assertSessionHasErrors(RecaptchaService::FIELD)
        ->assertSessionHasInput('subject', 'My site is down');

    expect(Ticket::where('client_id', $client->id)->count())->toBe(0);
});

it('opens no ticket when Google cannot be reached', function () {
    Http::fake([RecaptchaService::ENDPOINT => Http::response('', 500)]);
    recaptchaKeys();
    Setting::set('RecaptchaTicketsEnabled', '1');
    [$user, $client, $department] = recaptchaTicketOpener();

    postRecaptchaTicket($user, $department, [RecaptchaService::FIELD => 'some-answer'])
        ->assertSessionHasErrors(RecaptchaService::FIELD);

    expect(Ticket::where('client_id', $client->id)->count())->toBe(0);
});

it('opens the ticket when Google confirms the answer', function () {
    Http::fake([RecaptchaService::ENDPOINT => Http::response(['success' => true])]);
    recaptchaKeys();
    Setting::set('RecaptchaTicketsEnabled', '1');
    [$user, $client, $department] = recaptchaTicketOpener();

    postRecaptchaTicket($user, $department, [RecaptchaService::FIELD => 'good-answer'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(Ticket::where('client_id', $client->id)->count())->toBe(1);
    Http::assertSent(fn ($request) => $request->url() === RecaptchaService::ENDPOINT
        && $request['secret'] === 'secret-key-for-tests'
        && $request['response'] === 'good-answer');
});

it('saves the ticket switch from the admin settings form', function () {
    $admin = Admin::factory()->create([
        'role_id' => AdminRole::factory()->create([
            'name' => 'Settings',
            'permissions' => ['manage_settings'],
        ])->id,
    ]);

    test()->actingAs($admin, 'admin')->post(route('admin.settings.general.update'), [
        'RecaptchaEnabled' => '0',
        'RecaptchaTicketsEnabled' => '1',
    ])->assertRedirect();

    expect(Setting::get('RecaptchaTicketsEnabled'))->toBe('1')
        ->and(Setting::get('RecaptchaEnabled'))->toBe('0');

    test()->actingAs($admin, 'admin')->get(route('admin.settings.general'))
        ->assertOk()
        ->assertSee('name="RecaptchaTicketsEnabled"', false);
});
