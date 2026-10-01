<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use App\Services\RecaptchaService;
use Illuminate\Support\Facades\Http;

/**
 * Google reCAPTCHA on the public contact form.
 *
 * The contact form is the one way to open a ticket without an account, so it
 * is where automated spam arrives. The honeypot and the spam screen only stop
 * the crude kind; once the operator switches reCAPTCHA on, a submission must
 * carry an answer Google confirms, or no ticket is opened.
 */
function recaptchaDepartment(): TicketDepartment
{
    return TicketDepartment::create([
        'name' => 'General',
        'email' => 'dept@example.test',
        'hidden' => false,
        'sort_order' => 1,
    ]);
}

function recaptchaSettingsAdmin(): Admin
{
    return Admin::factory()->create([
        'role_id' => AdminRole::factory()->create([
            'name' => 'Settings',
            'permissions' => ['manage_settings'],
        ])->id,
    ]);
}

function enableRecaptcha(): void
{
    Setting::set('RecaptchaEnabled', '1');
    Setting::set('RecaptchaSiteKey', 'site-key-for-tests');
    Setting::set('RecaptchaSecretKey', 'secret-key-for-tests');
}

function postRecaptchaEnquiry(TicketDepartment $department, array $payload = [])
{
    return test()->post(route('client.contact.submit'), array_merge([
        'name' => 'Visitor',
        'email' => 'visitor@example.test',
        'department_id' => $department->id,
        'subject' => 'A question',
        'message' => 'Please get back to me.',
    ], $payload));
}

it('leaves the form as it was while reCAPTCHA is off', function () {
    Http::fake();
    $department = recaptchaDepartment();

    test()->get(route('client.contact'))->assertOk()->assertDontSee('g-recaptcha', false);
    postRecaptchaEnquiry($department)->assertSessionHasNoErrors();

    expect(Ticket::count())->toBe(1);
    Http::assertNothingSent();
});

it('counts a switch with a key missing as off', function () {
    Http::fake();
    Setting::set('RecaptchaEnabled', '1');
    Setting::set('RecaptchaSiteKey', 'site-key-for-tests');
    $department = recaptchaDepartment();

    expect(app(RecaptchaService::class)->enabled())->toBeFalse();
    test()->get(route('client.contact'))->assertOk()->assertDontSee('g-recaptcha', false);
    postRecaptchaEnquiry($department)->assertSessionHasNoErrors();

    expect(Ticket::count())->toBe(1);
});

it('shows the challenge with the site key once switched on', function () {
    enableRecaptcha();
    recaptchaDepartment();

    test()->get(route('client.contact'))
        ->assertOk()
        ->assertSee('data-sitekey="site-key-for-tests"', false)
        ->assertSee('https://www.google.com/recaptcha/api.js', false)
        ->assertDontSee('secret-key-for-tests');
});

it('opens no ticket when the challenge was not answered', function () {
    Http::fake();
    enableRecaptcha();
    $department = recaptchaDepartment();

    postRecaptchaEnquiry($department)->assertSessionHasErrors(RecaptchaService::FIELD);

    expect(Ticket::count())->toBe(0);
    Http::assertNothingSent();
});

it('opens no ticket when Google refuses the answer', function () {
    Http::fake([RecaptchaService::ENDPOINT => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']])]);
    enableRecaptcha();
    $department = recaptchaDepartment();

    postRecaptchaEnquiry($department, [RecaptchaService::FIELD => 'forged'])
        ->assertSessionHasErrors(RecaptchaService::FIELD)
        ->assertSessionHasInput('subject', 'A question');

    expect(Ticket::count())->toBe(0);
});

it('opens no ticket when Google cannot be reached', function () {
    Http::fake([RecaptchaService::ENDPOINT => Http::response('', 500)]);
    enableRecaptcha();
    $department = recaptchaDepartment();

    postRecaptchaEnquiry($department, [RecaptchaService::FIELD => 'some-answer'])
        ->assertSessionHasErrors(RecaptchaService::FIELD);

    expect(Ticket::count())->toBe(0);
});

it('opens the ticket when Google confirms the answer', function () {
    Http::fake([RecaptchaService::ENDPOINT => Http::response(['success' => true])]);
    enableRecaptcha();
    $department = recaptchaDepartment();

    postRecaptchaEnquiry($department, [RecaptchaService::FIELD => 'good-answer'])
        ->assertSessionHasNoErrors();

    expect(Ticket::count())->toBe(1);
    Http::assertSent(fn ($request) => $request->url() === RecaptchaService::ENDPOINT
        && $request['secret'] === 'secret-key-for-tests'
        && $request['response'] === 'good-answer');
});

it('keeps the stored secret when the admin form leaves it blank', function () {
    $admin = recaptchaSettingsAdmin();
    enableRecaptcha();

    test()->actingAs($admin, 'admin')->post(route('admin.settings.general.update'), [
        'RecaptchaEnabled' => '1',
        'RecaptchaSiteKey' => 'new-site-key',
        'RecaptchaSecretKey' => '',
    ])->assertRedirect();

    expect(Setting::get('RecaptchaSiteKey'))->toBe('new-site-key')
        ->and(Setting::get('RecaptchaSecretKey'))->toBe('secret-key-for-tests');
});

it('never prints the stored secret on the admin settings screen', function () {
    $admin = recaptchaSettingsAdmin();
    enableRecaptcha();

    test()->actingAs($admin, 'admin')->get(route('admin.settings.general'))
        ->assertOk()
        ->assertSee('site-key-for-tests')
        ->assertDontSee('secret-key-for-tests');
});
