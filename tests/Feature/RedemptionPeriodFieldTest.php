<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\DomainPricing;

/*
 * Domain sync moves an expired domain through grace and then redemption, and
 * the renewal reminders count both, using each extension's
 * redemption_grace_period. Domain Pricing had a field for the grace period
 * only, so redemption stayed 0 everywhere: a domain went from grace straight to
 * expired, and the reminders stopped while it could still be restored.
 */

function rpfAdmin(): Admin
{
    return Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);
}

test('an extension keeps its redemption period when added and when edited', function () {
    $this->actingAs(rpfAdmin(), 'admin')->post(route('admin.config.domain-pricing.store'), [
        'extension' => '.rpftest', 'register_price' => 10, 'transfer_price' => 10, 'renew_price' => 10,
        'grace_period' => 30, 'redemption_grace_period' => 30,
    ])->assertSessionHasNoErrors();

    $tld = DomainPricing::where('extension', '.rpftest')->sole();
    expect((int) $tld->redemption_grace_period)->toBe(30);

    $this->actingAs(rpfAdmin(), 'admin')->put(route('admin.config.domain-pricing.update', $tld), [
        'extension' => '.rpftest', 'grace_period' => 30, 'redemption_grace_period' => 40,
    ])->assertSessionHasNoErrors();
    expect((int) $tld->fresh()->redemption_grace_period)->toBe(40);

    $this->actingAs(rpfAdmin(), 'admin')->put(route('admin.config.domain-pricing.update', $tld), [
        'extension' => '.rpftest', 'redemption_grace_period' => -1,
    ])->assertSessionHasErrors('redemption_grace_period');
});

test('the form has the field and the list shows the period', function () {
    DomainPricing::create(['extension' => '.rpfshow', 'register_price' => 10, 'transfer_price' => 10, 'renew_price' => 10, 'grace_period' => 30, 'redemption_grace_period' => 30]);

    $this->actingAs(rpfAdmin(), 'admin')->get(route('admin.config.domain-pricing'))->assertOk()
        ->assertSee('name="redemption_grace_period"', false)
        ->assertSee("getElementById('tld-redemption').value = d.redemption_grace_period", false)
        ->assertSee('30d + 30d');
});
