<?php

use App\Models\Admin;
use App\Models\Client;
use App\Models\Setting;
use App\Models\User;
use App\Services\Sms\WhatsappPhoneVerifier;

/**
 * WhatsApp phone verification: the customer sends the code to the operator's
 * number and the Cloud API webhook (signed with the app secret) reports it.
 * Only a code we handed out, sent from the number on the profile, verifies.
 */
function whatsappOn(): void
{
    Setting::set('WhatsappVerifyEnabled', '1');
    Setting::set('WhatsappBusinessNumber', '+90 850 000 00 00');
    Setting::set('WhatsappAppSecret', 'app_secret_test');
    Setting::set('WhatsappWebhookVerifyToken', 'verify_token_test');
}

function whatsappClient(?string $phone = '0532 111 22 33', string $prefix = '+90'): array
{
    $user = User::factory()->create();
    $client = Client::factory()->create(['phone_number' => $phone, 'phone_prefix' => $prefix, 'phone_verified_at' => null]);
    $client->users()->attach($user->id, ['owner' => true]);

    return [$user, $client];
}

/** A Cloud API "messages" notification carrying one text message. */
function whatsappPayload(string $from, string $body, string $to = '908500000000'): array
{
    return ['object' => 'whatsapp_business_account', 'entry' => [['id' => 'WABA', 'changes' => [[
        'field' => 'messages',
        'value' => [
            'messaging_product' => 'whatsapp',
            'metadata' => ['display_phone_number' => $to, 'phone_number_id' => '123'],
            'contacts' => [['profile' => ['name' => 'Test'], 'wa_id' => $from]],
            'messages' => [['from' => $from, 'id' => 'wamid.1', 'timestamp' => '1', 'type' => 'text', 'text' => ['body' => $body]]],
        ],
    ]]]]];
}

function postWhatsapp($test, array $payload, string $secret = 'app_secret_test')
{
    $raw = json_encode($payload);

    return $test->call('POST', route('webhooks.whatsapp.receive'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $raw, $secret),
    ], $raw);
}

it('answers the Meta subscription handshake only with the right token', function () {
    whatsappOn();

    $this->get(route('webhooks.whatsapp').'?hub.mode=subscribe&hub.verify_token=verify_token_test&hub.challenge=abc123')
        ->assertOk()->assertSeeText('abc123');
    $this->get(route('webhooks.whatsapp').'?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=abc123')
        ->assertForbidden();
});

it('is not there while it is switched off', function () {
    $this->get(route('webhooks.whatsapp').'?hub.mode=subscribe&hub.verify_token=x&hub.challenge=1')->assertNotFound();
    postWhatsapp($this, whatsappPayload('905321112233', '123456'))->assertNotFound();
});

it('reads the stored phone as the international numbers it can stand for', function () {
    $client = new Client(['phone_number' => '0532 111 22 33', 'phone_prefix' => '+90']);
    expect(WhatsappPhoneVerifier::phoneCandidates($client))->toBe(['905321112233']);

    $client = new Client(['phone_number' => '+90 532 111 22 33', 'phone_prefix' => '+90']);
    expect(WhatsappPhoneVerifier::phoneCandidates($client))->toBe(['905321112233']);

    $client = new Client(['phone_number' => '90 532 111 22 33', 'phone_prefix' => '+90']);
    expect(WhatsappPhoneVerifier::phoneCandidates($client))->toContain('905321112233');

    expect(WhatsappPhoneVerifier::phoneCandidates(new Client(['phone_number' => ''])))->toBe([]);
});

it('hands out a code and a wa.me link to the business number', function () {
    whatsappOn();
    [$user, $client] = whatsappClient();

    $this->actingAs($user)->post(route('client.account.phone.whatsapp'))->assertRedirect();

    $pending = app(WhatsappPhoneVerifier::class)->pending($client);
    expect($pending['code'])->toMatch('/^\d{6}$/')
        ->and($pending['link'])->toStartWith('https://wa.me/908500000000?text=')
        ->and(rawurldecode($pending['link']))->toContain($pending['code']);

    $this->actingAs($user)->get(route('client.account.security'))
        ->assertOk()->assertSee($pending['code'])->assertSee('https://wa.me/908500000000', false);
});

it('verifies the phone when the code comes from the number on the profile', function () {
    whatsappOn();
    [$user, $client] = whatsappClient();
    $code = app(WhatsappPhoneVerifier::class)->start($client)['code'];

    postWhatsapp($this, whatsappPayload('905321112233', "My verification code: {$code}"))->assertOk();

    expect($client->fresh()->phone_verified_at)->not->toBeNull()
        ->and(app(WhatsappPhoneVerifier::class)->pending($client))->toBeNull();
    $this->actingAs($user)->getJson(route('client.account.phone.whatsapp_status'))->assertJson(['verified' => true]);
});

it('does not verify a code sent from another number, and says so', function () {
    whatsappOn();
    [$user, $client] = whatsappClient();
    $code = app(WhatsappPhoneVerifier::class)->start($client)['code'];

    postWhatsapp($this, whatsappPayload('905559998877', $code))->assertOk();

    expect($client->fresh()->phone_verified_at)->toBeNull();
    $this->actingAs($user)->getJson(route('client.account.phone.whatsapp_status'))
        ->assertJson(['verified' => false, 'state' => 'mismatch']);
});

it('refuses a notification that is not signed with the app secret', function () {
    whatsappOn();
    [, $client] = whatsappClient();
    $code = app(WhatsappPhoneVerifier::class)->start($client)['code'];

    postWhatsapp($this, whatsappPayload('905321112233', $code), 'another_secret')->assertForbidden();

    expect($client->fresh()->phone_verified_at)->toBeNull();
});

it('ignores codes it did not hand out and messages to another number', function () {
    whatsappOn();
    [, $client] = whatsappClient();
    $code = app(WhatsappPhoneVerifier::class)->start($client)['code'];
    $other = $code === '111111' ? '222222' : '111111';

    postWhatsapp($this, whatsappPayload('905321112233', $other))->assertOk();
    postWhatsapp($this, whatsappPayload('905321112233', $code, '15550001111'))->assertOk();

    expect($client->fresh()->phone_verified_at)->toBeNull();
});

it('lets a new code replace the old one', function () {
    whatsappOn();
    [, $client] = whatsappClient();
    $verifier = app(WhatsappPhoneVerifier::class);
    $first = $verifier->start($client)['code'];
    $second = $verifier->start($client)['code'];

    if ($first !== $second) {
        postWhatsapp($this, whatsappPayload('905321112233', $first))->assertOk();
        expect($client->fresh()->phone_verified_at)->toBeNull();
    }

    postWhatsapp($this, whatsappPayload('905321112233', $second))->assertOk();
    expect($client->fresh()->phone_verified_at)->not->toBeNull();
});

it('forgets the verification when the phone number changes', function () {
    [, $client] = whatsappClient();
    $client->forceFill(['phone_verified_at' => now()])->save();

    $client->update(['phone_number' => '0532 111 22 33']);
    expect($client->fresh()->phone_verified_at)->not->toBeNull();

    $client->update(['phone_number' => '0533 444 55 66']);
    expect($client->fresh()->phone_verified_at)->toBeNull();
});

it('offers the WhatsApp fields on the settings screen and keeps a blank secret', function () {
    Setting::set('WhatsappAppSecret', 'existing_secret');
    $admin = Admin::factory()->create();

    $html = $this->actingAs($admin, 'admin')->get(route('admin.settings.general'))->assertOk()->getContent();
    expect($html)->toContain('name="WhatsappVerifyEnabled"')->toContain('name="WhatsappBusinessNumber"')
        ->toContain('name="WhatsappAppSecret"')->toContain('name="WhatsappWebhookVerifyToken"')
        ->toContain(route('webhooks.whatsapp'))
        ->not->toContain('existing_secret');

    $this->actingAs($admin, 'admin')->post(route('admin.settings.general.update'), [
        'CompanyName' => 'Test Co', 'WhatsappVerifyEnabled' => '1', 'WhatsappBusinessNumber' => '+90 850 000 00 00',
        'WhatsappAppSecret' => '', 'WhatsappWebhookVerifyToken' => 'tok',
    ])->assertRedirect();

    expect(Setting::get('WhatsappAppSecret'))->toBe('existing_secret')
        ->and((int) Setting::get('WhatsappVerifyEnabled'))->toBe(1)
        ->and(Setting::get('WhatsappWebhookVerifyToken'))->toBe('tok');
});
