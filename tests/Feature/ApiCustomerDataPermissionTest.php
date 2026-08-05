<?php

use App\Constants\Permissions;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\ApiCredential;
use App\Models\Client;
use App\Models\Email;
use Database\Factories\ApiCredentialFactory;

/**
 * Which permission answers for customer data at the API.
 *
 * An API call is answered with the permissions its caller would need to do the
 * same thing by hand, worked out from the controller. Everything in
 * SystemApiController reads under "view system" - and that controller also
 * serves the mail history and the activity log, which are customer records,
 * not system information. A member of staff trusted to look at the version
 * number could read every customer's mail, every invoice notice, every ticket
 * reply that had been sent.
 */
function scopedCredentialFor(array $permissions): array
{
    $role = AdminRole::create([
        'name' => 'Scoped '.uniqid(),
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

function storedCustomerMail(): Email
{
    return Email::create([
        'client_id' => Client::factory()->create()->id,
        'subject' => 'Invoice #INV-SECRET',
        'message' => 'The whole message body',
        'date' => now(),
        'to' => 'someone@test.local',
        'pending' => false,
        'failed' => false,
    ]);
}

it('does not hand customer mail to someone who may only see the system', function () {
    storedCustomerMail();

    $this->withHeaders(scopedCredentialFor([Permissions::VIEW_SYSTEM]))
        ->getJson('/api/v1/getemails')
        ->assertStatus(403);
});

it('hands customer mail to someone who may see customers', function () {
    storedCustomerMail();

    $this->withHeaders(scopedCredentialFor([Permissions::VIEW_SYSTEM, Permissions::LIST_CLIENTS]))
        ->getJson('/api/v1/getemails')
        ->assertSuccessful();
});

it('still answers a system question for the system permission', function () {
    $this->withHeaders(scopedCredentialFor([Permissions::VIEW_SYSTEM]))
        ->getJson('/api/v1/pnlcsdetails')
        ->assertSuccessful();
});

it('does not hand the activity log to someone who may only see the system', function () {
    $this->withHeaders(scopedCredentialFor([Permissions::VIEW_SYSTEM]))
        ->getJson('/api/v1/getactivitylog')
        ->assertStatus(403);
});
