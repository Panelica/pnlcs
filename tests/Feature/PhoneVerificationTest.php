<?php

use App\Models\Client;
use App\Models\Setting;
use App\Models\User;
use App\Services\Sms\TwilioVerifyClient;
use Illuminate\Support\Facades\Http;

/**
 * Twilio Verify, held to the published wire contract: form-encoded POSTs with
 * Basic auth to Services/{sid}/Verifications (To + Channel) and
 * Services/{sid}/VerificationCheck (To + Code); only an "approved" status
 * verifies the phone, and a 404 from the check endpoint is a definite "gone"
 * (expired or attempts used up), not an outage.
 */
function twilioOn(): void
{
    Setting::set('TwilioVerifyEnabled', '1');
    Setting::set('TwilioAccountSid', 'AC_test_sid');
    Setting::set('TwilioAuthToken', 'test_token');
    Setting::set('TwilioVerifyServiceSid', 'VA_test_service');
}

function phoneClient(?string $phone = '532 111 22 33', string $prefix = '+90'): array
{
    $user = User::factory()->create();
    $client = Client::factory()->create(['phone_number' => $phone, 'phone_prefix' => $prefix]);
    $client->users()->attach($user->id, ['owner' => true]);

    return [$user, $client];
}

it('turns a stored phone into E.164', function () {
    expect(TwilioVerifyClient::e164('+90 532 111 22 33'))->toBe('+905321112233')
        ->and(TwilioVerifyClient::e164('nonsense'))->toBeNull()
        ->and(TwilioVerifyClient::e164(null))->toBeNull();
});

it('sends the verification start exactly as the spec asks', function () {
    Http::fake(['verify.twilio.com/*' => Http::response(['sid' => 'VE1', 'status' => 'pending'], 201)]);
    twilioOn();

    app(TwilioVerifyClient::class)->start('+905321112233');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://verify.twilio.com/v2/Services/VA_test_service/Verifications'
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('AC_test_sid:test_token'))
            && $request['To'] === '+905321112233'
            && $request['Channel'] === 'sms';
    });
});

it('verifies the phone when the check comes back approved', function () {
    Http::fake(['verify.twilio.com/*' => Http::response(['status' => 'approved', 'valid' => true], 200)]);
    twilioOn();
    [$user, $client] = phoneClient();

    $this->actingAs($user)
        ->post(route('client.account.phone.verify_check'), ['code' => '123456'])
        ->assertRedirect();

    expect($client->fresh()->phone_verified_at)->not->toBeNull();
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/VerificationCheck')
        && $r['To'] === '+905321112233' && $r['Code'] === '123456');
});

it('does not verify on a wrong code', function () {
    Http::fake(['verify.twilio.com/*' => Http::response(['status' => 'pending', 'valid' => false], 200)]);
    twilioOn();
    [$user, $client] = phoneClient();

    $this->actingAs($user)
        ->post(route('client.account.phone.verify_check'), ['code' => '000000'])
        ->assertRedirect()
        ->assertSessionHasErrors('code');

    expect($client->fresh()->phone_verified_at)->toBeNull();
});

it('treats a 404 from the check as expired, not verified', function () {
    Http::fake(['verify.twilio.com/*' => Http::response(['code' => 20404], 404)]);
    twilioOn();
    [$user, $client] = phoneClient();

    $this->actingAs($user)
        ->post(route('client.account.phone.verify_check'), ['code' => '123456'])
        ->assertSessionHasErrors('code');

    expect($client->fresh()->phone_verified_at)->toBeNull();
});

it('hides the whole feature when the operator has not configured it', function () {
    Http::fake();
    [$user] = phoneClient();

    $this->actingAs($user)->post(route('client.account.phone.verify'))->assertNotFound();
    $this->actingAs($user)->post(route('client.account.phone.verify_check'), ['code' => '123456'])->assertNotFound();
    Http::assertNothingSent();
});

it('asks for a phone number before offering to send a code', function () {
    Http::fake();
    twilioOn();
    [$user] = phoneClient(null);

    $this->actingAs($user)->post(route('client.account.phone.verify'))
        ->assertRedirect()
        ->assertSessionHasErrors('phone');
    Http::assertNothingSent();
});

it('shows the verification card on the security page only when configured', function () {
    [$user] = phoneClient();

    $off = $this->actingAs($user)->get(route('client.account.security'))->assertOk()->getContent();
    expect($off)->not->toContain(__('client.phone_verify.title'));

    twilioOn();
    $on = $this->actingAs($user)->get(route('client.account.security'))->assertOk()->getContent();
    expect($on)->toContain(__('client.phone_verify.title'));
});

it('offers the Twilio fields on the settings screen and keeps a blank token', function () {
    Setting::set('TwilioAuthToken', 'existing_token');
    $admin = App\Models\Admin::factory()->create();

    $html = $this->actingAs($admin, 'admin')->get(route('admin.settings.general'))->assertOk()->getContent();
    expect($html)->toContain('name="TwilioVerifyEnabled"')->toContain('name="TwilioAccountSid"')
        ->toContain('name="TwilioAuthToken"')->toContain('name="TwilioVerifyServiceSid"')
        ->not->toContain('existing_token');

    $this->actingAs($admin, 'admin')->post(route('admin.settings.general.update'), [
        'CompanyName' => 'Test Co', 'TwilioVerifyEnabled' => '1',
        'TwilioAccountSid' => 'AC9', 'TwilioAuthToken' => '', 'TwilioVerifyServiceSid' => 'VA9',
    ])->assertRedirect();

    expect(Setting::get('TwilioAuthToken'))->toBe('existing_token')
        ->and((int) Setting::get('TwilioVerifyEnabled'))->toBe(1);
});
