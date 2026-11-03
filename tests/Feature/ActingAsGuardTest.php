<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\User;

/*
 * Tests sign in as staff and as a customer in the same test, and Laravel's
 * actingAs() makes whichever guard it is given the default one for the rest of
 * that test. A later actingAs($customer) with no guard therefore put the
 * customer on the admin guard - where the fingerprint that ends a session after
 * a password change (EndSessionAfterPasswordChange) found the staff fingerprint
 * of the same id, read a different password and signed the request out. The
 * customer's page then answered as a guest and redirected to the sign-in page.
 *
 * It only struck when the two accounts had been handed the same id, so which
 * test broke depended on how many rows the run had created before it: adding a
 * test file anywhere moved the failure somewhere else. Tests\TestCase::actingAs
 * names the guard the account belongs to; this pins it with the two ids
 * deliberately equal, which is the case that used to break.
 */
test('a customer signed in after staff stays on their own guard', function () {
    $admin = Admin::factory()->make(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);
    $admin->id = 900001;
    $admin->save();

    $user = User::factory()->make();
    $user->id = 900001;
    $user->save();
    $user->clients()->attach(Client::factory()->create()->id);

    $this->actingAs($admin, 'admin')->get(route('admin.dashboard'))->assertOk();

    $this->actingAs($user)->get(route('client.home'))->assertOk();

    expect(auth('web')->id())->toBe($user->id);
});
