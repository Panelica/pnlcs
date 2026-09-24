<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\ApiCredential;
use Database\Factories\ApiCredentialFactory;

/*
 * The addresses a credential may be used from.
 *
 * ApiKeyAuth enforced allowed_ips, but nothing could set them: the credential
 * screen had only a description field and the API had no parameter. An
 * operator reading that credentials can be restricted by address had no way
 * to do it. And a credential could not be switched off, only deleted.
 */

function allowListAdmin(): Admin
{
    return Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);
}

test('the credential screen stores the allowed addresses, one per line', function () {
    $this->actingAs(allowListAdmin(), 'admin')
        ->post(route('admin.config.api-credentials.store'), [
            'description' => 'CRM',
            'allowed_ips' => "203.0.113.7\n198.51.100.0/24\n\n2001:db8::/32",
        ])->assertSessionHasNoErrors();

    expect(ApiCredential::where('description', 'CRM')->first()->allowed_ips)
        ->toBe(['203.0.113.7', '198.51.100.0/24', '2001:db8::/32']);
});

test('an entry that is not an address or a range is refused, by name', function () {
    $this->actingAs(allowListAdmin(), 'admin')
        ->post(route('admin.config.api-credentials.store'), [
            'description' => 'CRM',
            'allowed_ips' => "203.0.113.7\nexample.com",
        ])->assertSessionHasErrors(['allowed_ips' => __('admin.api_credentials.invalid_ip', ['value' => 'example.com'])]);

    foreach (['10.0.0.0/33', '2001:db8::/129', '300.1.1.1'] as $bad) {
        $this->post(route('admin.config.api-credentials.store'), ['description' => 'x', 'allowed_ips' => $bad])
            ->assertSessionHasErrors('allowed_ips');
    }

    expect(ApiCredential::count())->toBe(0);
});

test('editing changes the description and addresses and can switch the credential off', function () {
    $credential = ApiCredential::factory()->create(['description' => 'Old']);

    $this->actingAs(allowListAdmin(), 'admin')
        ->put(route('admin.config.api-credentials.update', $credential), [
            'description' => 'New',
            'allowed_ips' => '192.0.2.10',
        ])->assertSessionHasNoErrors();

    $credential->refresh();
    expect($credential->description)->toBe('New')
        ->and($credential->allowed_ips)->toBe(['192.0.2.10'])
        ->and($credential->active)->toBeFalse();

    // Switched off, it no longer opens the API.
    $this->withHeaders(['X-API-Key' => $credential->identifier, 'X-API-Secret' => ApiCredentialFactory::PLAINTEXT_SECRET])
        ->getJson('/api/v1/getstats')->assertStatus(401);
});

test('the list says where each credential may be used from', function () {
    ApiCredential::factory()->create(['description' => 'Restricted', 'allowed_ips' => ['203.0.113.7']]);
    ApiCredential::factory()->create(['description' => 'Open']);

    $this->actingAs(allowListAdmin(), 'admin')
        ->get(route('admin.config.api-credentials'))
        ->assertOk()
        ->assertSee('203.0.113.7')
        ->assertSee(__('admin.api_credentials.anywhere'))
        ->assertSee(route('admin.config.api-credentials.update', ApiCredential::first()), false);
});

test('the API sets and replaces the allowed addresses, and they are enforced', function () {
    $caller = ApiCredential::factory()->create();
    $headers = ['X-API-Key' => $caller->identifier, 'X-API-Secret' => ApiCredentialFactory::PLAINTEXT_SECRET];

    $created = $this->withHeaders($headers)->postJson('/api/v1/createoauthcredential', [
        'description' => 'Script', 'allowed_ips' => '203.0.113.7, 198.51.100.0/24',
    ])->assertOk()->assertJsonPath('allowed_ips', ['203.0.113.7', '198.51.100.0/24']);

    $new = ['X-API-Key' => $created->json('identifier'), 'X-API-Secret' => $created->json('secret')];

    // From an address outside the list: refused.
    $this->withHeaders($new)->withServerVariables(['REMOTE_ADDR' => '192.0.2.1'])
        ->getJson('/api/v1/getstats')->assertStatus(403);
    // From one inside a listed range: answered.
    $this->withHeaders($new)->withServerVariables(['REMOTE_ADDR' => '198.51.100.42'])
        ->getJson('/api/v1/getstats')->assertOk();

    $this->withHeaders($headers)->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
        ->postJson('/api/v1/updateoauthcredential', ['credentialid' => $created->json('credentialid'), 'allowed_ips' => 'nonsense'])
        ->assertStatus(422)->assertJsonPath('result', 'error');

    $this->postJson('/api/v1/updateoauthcredential', ['credentialid' => $created->json('credentialid'), 'allowed_ips' => ''])
        ->assertOk();
    expect(ApiCredential::find($created->json('credentialid'))->allowed_ips)->toBeNull();

    $this->getJson('/api/v1/listoauthcredentials')->assertOk()
        ->assertJsonStructure(['credentials' => [['id', 'identifier', 'description', 'allowed_ips', 'created_at']]]);
});
