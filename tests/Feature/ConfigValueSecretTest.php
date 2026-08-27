<?php

use App\Models\Admin;
use App\Models\ApiCredential;
use App\Models\Setting;

/**
 * getconfigurationvalue refuses to hand a credential back through the API -
 * but the filter matched "api_key", not the "...Key" the credential settings
 * are actually named, so a MaxMind license key and a Twilio service SID were
 * readable in the clear. Anything holding a secret must be refused.
 */
function cfgHeaders(): array
{
    $admin = Admin::factory()->create();
    ApiCredential::create(['admin_id' => $admin->id, 'identifier' => 'cfg_k', 'secret' => ApiCredential::hashSecret('cfg_s'), 'active' => true]);

    return ['X-API-Key' => 'cfg_k', 'X-API-Secret' => 'cfg_s'];
}

it('refuses to read a stored credential-bearing setting', function (string $key) {
    Setting::set($key, 'super-secret-value');

    test()->getJson('/api/v1/getconfigurationvalue?setting='.$key, cfgHeaders())
        ->assertStatus(403);
})->with([
    'MaxMindLicenseKey',
    'FraudLabsApiKey',
    'TwilioAuthToken',
    'TwilioVerifyServiceSid',
    'SMTPPassword',
    'OpenAIApiKey',
]);

it('refuses to write a stored credential-bearing setting', function () {
    test()->postJson('/api/v1/setconfigurationvalue', ['setting' => 'MaxMindLicenseKey', 'value' => 'x'], cfgHeaders())
        ->assertStatus(403);
});

it('still reads an ordinary non-secret setting', function () {
    Setting::set('CompanyName', 'Acme');

    test()->getJson('/api/v1/getconfigurationvalue?setting=CompanyName', cfgHeaders())
        ->assertOk()
        ->assertJsonPath('value', 'Acme');
});
