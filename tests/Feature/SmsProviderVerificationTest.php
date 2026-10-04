<?php

use App\Contracts\PhoneVerifier;
use App\Contracts\SmsSender;
use App\Models\Client;
use App\Models\Setting;
use App\Models\User;
use App\Services\Sms\SmsCodeVerifier;
use App\Services\Sms\TwilioVerifyClient;

/*
 * Phone verification was tied to Twilio Verify. A host using a local SMS
 * provider (NetGSM and the like in Turkey) could not verify phones at all.
 * An addon can now bind an SmsSender; when Twilio is not set up, codes of
 * our own go out through it.
 */

class SmsProviderTestSender implements SmsSender
{
    public array $sent = [];

    public function send(string $to, string $message): bool
    {
        $this->sent[] = [$to, $message];

        return true;
    }
}

function spvClient(): array
{
    $user = User::factory()->create();
    $client = Client::factory()->create(['phone_number' => '532 111 22 33', 'phone_prefix' => '+90']);
    $user->clients()->attach($client->id, ['owner' => true]);

    return [$user, $client];
}

test('without an SMS sender Twilio Verify is used, as before', function () {
    expect(app(PhoneVerifier::class))->toBeInstanceOf(TwilioVerifyClient::class);
});

test('with an SMS sender and no Twilio, a code of our own goes out through it and verifies the phone', function () {
    $sender = new SmsProviderTestSender;
    app()->instance(SmsSender::class, $sender);
    [$user, $client] = spvClient();

    expect(app(PhoneVerifier::class))->toBeInstanceOf(SmsCodeVerifier::class);
    $this->actingAs($user)->post(route('client.account.phone.verify'))->assertSessionHas('phone_code_sent');

    expect($sender->sent)->toHaveCount(1)->and($sender->sent[0][0])->toBe('+905321112233');
    preg_match('/\b(\d{6})\b/', $sender->sent[0][1], $m);

    $this->actingAs($user)->post(route('client.account.phone.verify_check'), ['code' => '000000'])->assertSessionHasErrors('code');
    $this->actingAs($user)->post(route('client.account.phone.verify_check'), ['code' => $m[1]])->assertSessionHas('success');
    expect($client->fresh()->phone_verified_at)->not->toBeNull();
});

test('a code stops working after five wrong tries', function () {
    $sender = new SmsProviderTestSender;
    $verifier = new SmsCodeVerifier($sender);
    $verifier->start('+905321112233');
    preg_match('/\b(\d{6})\b/', $sender->sent[0][1], $m);

    foreach (range(1, SmsCodeVerifier::MAX_ATTEMPTS) as $try) {
        $verifier->check('+905321112233', '000000');
    }

    expect($verifier->check('+905321112233', $m[1]))->toBe(['status' => 'expired', 'approved' => false]);
});

test('Twilio Verify still wins when it is set up', function () {
    app()->instance(SmsSender::class, new SmsProviderTestSender);
    Setting::set('TwilioVerifyEnabled', '1');
    Setting::set('TwilioAccountSid', 'AC_x');
    Setting::set('TwilioAuthToken', 't');
    Setting::set('TwilioVerifyServiceSid', 'VA_x');

    expect(app(PhoneVerifier::class))->toBeInstanceOf(TwilioVerifyClient::class);
});
