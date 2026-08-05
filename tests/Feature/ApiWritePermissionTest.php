<?php

use App\Constants\Permissions;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\ApiCredential;
use App\Models\Client;
use Database\Factories\ApiCredentialFactory;

/**
 * Which permission answers for the writes in SystemApiController.
 *
 * Everything that writes there answers to "manage settings", because that is
 * what the controller as a whole is mapped to. But the controller is where the
 * odds and ends live: banning an address is a security decision, publishing an
 * announcement is a public statement, and the admin notes on a customer are
 * that customer's record. A member of staff trusted to change the company
 * address could ban addresses, publish notices in the operator's name, and
 * rewrite what the file says about a customer.
 */
function writeCredentialFor(array $permissions): array
{
    $role = AdminRole::create([
        'name' => 'Writer '.uniqid(),
        'permissions' => $permissions,
        'is_full_admin' => false,
    ]);

    $admin = Admin::factory()->create(['role_id' => $role->id]);
    $credential = ApiCredential::factory()->create(['admin_id' => $admin->id]);

    return [
        'X-API-Key' => $credential->identifier,
        'X-API-Secret' => ApiCredentialFactory::PLAINTEXT_SECRET,
    ];
}

it('does not let the settings permission ban an address', function () {
    $this->withHeaders(writeCredentialFor([Permissions::MANAGE_SETTINGS]))
        ->postJson('/api/v1/addbannedip', ['ip' => '203.0.113.9', 'reason' => 'test'])
        ->assertStatus(403);
});

it('lets the security permission ban an address', function () {
    $this->withHeaders(writeCredentialFor([Permissions::MANAGE_SECURITY]))
        ->postJson('/api/v1/addbannedip', ['ip' => '203.0.113.10', 'reason' => 'test'])
        ->assertSuccessful();
});

it('does not let the settings permission publish an announcement', function () {
    $this->withHeaders(writeCredentialFor([Permissions::MANAGE_SETTINGS]))
        ->postJson('/api/v1/addannouncement', ['title' => 'Notice', 'announcement' => 'Body'])
        ->assertStatus(403);
});

it('does not let the settings permission rewrite a customer record', function () {
    $client = Client::factory()->create(['notes' => 'what the file says']);

    $this->withHeaders(writeCredentialFor([Permissions::MANAGE_SETTINGS]))
        ->postJson('/api/v1/updateadminnotes', ['clientid' => $client->id, 'notes' => 'rewritten'])
        ->assertStatus(403);

    expect($client->fresh()->notes)->toBe('what the file says');
});

it('lets the customer permission write the customer note', function () {
    $client = Client::factory()->create(['notes' => 'what the file says']);

    $this->withHeaders(writeCredentialFor([Permissions::EDIT_CLIENTS]))
        ->postJson('/api/v1/updateadminnotes', ['clientid' => $client->id, 'notes' => 'rewritten'])
        ->assertSuccessful();

    expect($client->fresh()->notes)->toBe('rewritten');
});

it('says the send-email endpoints do nothing', function () {
    foreach (['sendemail', 'sendadminemail'] as $endpoint) {
        $this->withHeaders(writeCredentialFor([Permissions::MANAGE_SETTINGS]))
            ->postJson('/api/v1/'.$endpoint, ['messagename' => 'x'])
            ->assertStatus(501);
    }
});
