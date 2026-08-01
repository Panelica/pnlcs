<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\NetworkIssue;
use Illuminate\Support\Facades\Hash;

/**
 * Two-factor authentication for staff, which nobody could switch on.
 *
 * The verify screen, the middleware, the setup page and the disable endpoint
 * all exist. Nothing links to the setup page - its only mention anywhere is
 * the form on the page itself, posting back to itself - so no member of staff
 * could reach it. Not one of the administrators on this installation has it
 * on, and none of them could have.
 *
 * The customer side has had this on its security page all along.
 */
function accountAdmin(bool $withTwoFactor = false): Admin
{
    return Admin::factory()->create([
        'password' => Hash::make('the-admin-password'),
        'role_id' => AdminRole::factory()->fullAdmin()->create()->id,
        'second_factor_type' => $withTwoFactor ? 'totp' : null,
        'second_factor_secret' => $withTwoFactor ? 'JBSWY3DPEHPK3PXP' : null,
    ]);
}

test('an administrator without it is offered it', function () {
    $this->actingAs(accountAdmin(), 'admin')
        ->get(route('admin.my-account'))
        ->assertOk()
        ->assertSee(route('admin.2fa.enable'), false);
});

test('an administrator with it is offered a way off', function () {
    $this->withSession(['admin_2fa_verified' => true])
        ->actingAs(accountAdmin(withTwoFactor: true), 'admin')
        ->get(route('admin.my-account'))
        ->assertOk()
        ->assertSee(route('admin.2fa.disable'), false);
});

test('turning it off needs the account password', function () {
    $admin = accountAdmin(withTwoFactor: true);

    $this->withSession(['admin_2fa_verified' => true])
        ->actingAs($admin, 'admin')
        ->post(route('admin.2fa.disable'), ['password' => 'not-the-password'])
        ->assertSessionHasErrors('password');

    expect($admin->fresh()->second_factor_type)->toBe('totp');
});

test('the right password turns it off', function () {
    $admin = accountAdmin(withTwoFactor: true);

    $this->withSession(['admin_2fa_verified' => true])
        ->actingAs($admin, 'admin')
        ->post(route('admin.2fa.disable'), ['password' => 'the-admin-password'])
        ->assertRedirect();

    expect($admin->fresh()->second_factor_type)->toBeNull();
});

test('a network issue can be corrected after it is reported', function () {
    $issue = NetworkIssue::create([
        'title' => 'Packet loss in Frankfurt',
        'type' => 'network',
        'priority' => 'high',
        'status' => 'investigating',
        'description' => 'Upstream carrier reports congestion.',
        'start_date' => now(),
    ]);

    $html = $this->actingAs(accountAdmin(), 'admin')
        ->get(route('admin.config.network-issues'))
        ->assertOk()
        ->getContent();

    // Update and destroy share a URL, so the method has to be checked too.
    expect($html)->toContain(route('admin.config.network-issues.update', $issue))
        ->and($html)->toContain('value="PUT"');

    $this->actingAs(accountAdmin(), 'admin')
        ->put(route('admin.config.network-issues.update', $issue), [
            'title' => 'Packet loss in Frankfurt',
            'type' => 'network',
            'priority' => 'low',
            'status' => 'resolved',
            'description' => 'Carrier confirms the route is clear.',
            'start_date' => now()->toDateString(),
        ])->assertRedirect();

    expect($issue->fresh()->status)->toBe('resolved');
});
