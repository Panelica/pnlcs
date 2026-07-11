<?php

use App\Models\Admin;
use App\Models\ApiCredential;

/**
 * Per-credential IP allowlist enforcement. allowed_ips (JSON array of IPs or
 * CIDR ranges) restricts where a credential may be used; an empty/unset list
 * means no restriction.
 */

function ipCredHeaders(?array $allowedIps): array
{
    $admin = Admin::factory()->create();
    ApiCredential::create([
        'admin_id'    => $admin->id,
        'identifier'  => 'ipid',
        'secret'      => 'ipsec',
        'active'      => true,
        'allowed_ips' => $allowedIps,
    ]);
    return ['X-API-Key' => 'ipid', 'X-API-Secret' => 'ipsec'];
}

it('allows any IP when allowed_ips is empty', function () {
    $h = ipCredHeaders(null);
    $this->withServerVariables(['REMOTE_ADDR' => '9.9.9.9'])
        ->getJson('/api/v1/getstats', $h)->assertOk();
});

it('rejects a request from an IP not in allowed_ips', function () {
    $h = ipCredHeaders(['1.2.3.4']);
    $this->withServerVariables(['REMOTE_ADDR' => '9.9.9.9'])
        ->getJson('/api/v1/getstats', $h)->assertStatus(403);
});

it('allows a request from an IP listed in allowed_ips', function () {
    $h = ipCredHeaders(['9.9.9.9', '1.2.3.4']);
    $this->withServerVariables(['REMOTE_ADDR' => '9.9.9.9'])
        ->getJson('/api/v1/getstats', $h)->assertOk();
});

it('allows a request from an IP inside a CIDR range', function () {
    $h = ipCredHeaders(['10.0.0.0/8']);
    $this->withServerVariables(['REMOTE_ADDR' => '10.5.6.7'])
        ->getJson('/api/v1/getstats', $h)->assertOk();
});
