<?php

use App\Models\ApiCredential;
use App\Models\Setting;
use Database\Factories\ApiCredentialFactory;

/**
 * Reading and writing settings through the API.
 *
 * getconfigurationvalue returned whatever setting it was asked for. The
 * settings table is where the mail password is kept, in plain text, put there
 * by the settings screen - so a credential with nothing more than read access
 * could ask for SMTPPassword and be given it.
 *
 * setconfigurationvalue wrote whatever it was asked to write, into the
 * "general" group. Naming a setting that belongs to another screen moved it
 * there, and the screen that owns it looks it up by group - the same mistake
 * the settings form was hardened against, left open at the other door.
 */
function configApiHeaders(): array
{
    $credential = ApiCredential::factory()->create();

    return [
        'X-API-Key' => $credential->identifier,
        'X-API-Secret' => ApiCredentialFactory::PLAINTEXT_SECRET,
    ];
}

it('does not hand out the mail password', function () {
    Setting::set('SMTPPassword', 'the-mail-password', 'general');

    $response = $this->withHeaders(configApiHeaders())
        ->getJson('/api/v1/getconfigurationvalue?setting=SMTPPassword');

    expect($response->json('value'))->not->toBe('the-mail-password');
    expect($response->status())->toBe(403);
});

it('still hands out an ordinary setting', function () {
    Setting::set('CompanyName', 'Test Co', 'general');

    $this->withHeaders(configApiHeaders())
        ->getJson('/api/v1/getconfigurationvalue?setting=CompanyName')
        ->assertSuccessful()
        ->assertJson(['value' => 'Test Co']);
});

it('refuses to write a secret', function () {
    $this->withHeaders(configApiHeaders())
        ->postJson('/api/v1/setconfigurationvalue', [
            'setting' => 'SMTPPassword',
            'value' => 'set-from-the-api',
        ])->assertStatus(403);

    expect(Setting::get('SMTPPassword'))->not->toBe('set-from-the-api');
});

it('leaves a setting in the group its screen looks in', function () {
    Setting::set('dark_mode_enabled', '1', 'appearance');

    $this->withHeaders(configApiHeaders())
        ->postJson('/api/v1/setconfigurationvalue', [
            'setting' => 'dark_mode_enabled',
            'value' => '0',
        ])->assertSuccessful();

    expect(Setting::where('setting', 'dark_mode_enabled')->value('group'))->toBe('appearance')
        ->and(Setting::get('dark_mode_enabled'))->toBe('0');
});
