<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Currency;

/**
 * Exchange rates are stored against the default currency, whose rate is 1.
 * "Make default" moved only the flag: the new default kept its old rate and
 * every other rate stayed relative to the previous default until the next rate
 * update, which runs only when automatic updates are on.
 */
function rebaseAdmin(): Admin
{
    return Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);
}

it('restates every rate against the new default', function () {
    Currency::query()->update(['is_default' => false]);
    $eur = Currency::factory()->create(['code' => 'EUX', 'rate' => 1, 'is_default' => true]);
    $try = Currency::factory()->create(['code' => 'TRX', 'rate' => 40, 'is_default' => false]);
    $usd = Currency::factory()->create(['code' => 'USX', 'rate' => 1.25, 'is_default' => false]);

    $this->actingAs(rebaseAdmin(), 'admin')
        ->post(route('admin.config.currencies.default', $try))->assertRedirect();

    expect((float) $try->fresh()->rate)->toBe(1.0)
        ->and($try->fresh()->is_default)->toBeTruthy()
        ->and((float) $eur->fresh()->rate)->toBe(0.025)
        ->and((float) $usd->fresh()->rate)->toBe(0.03125)
        ->and($eur->fresh()->is_default)->toBeFalsy();
});

it('changes nothing when the currency already is the default', function () {
    Currency::query()->update(['is_default' => false]);
    $eur = Currency::factory()->create(['code' => 'EUY', 'rate' => 1, 'is_default' => true]);
    $try = Currency::factory()->create(['code' => 'TRY', 'rate' => 40, 'is_default' => false]);

    $this->actingAs(rebaseAdmin(), 'admin')
        ->post(route('admin.config.currencies.default', $eur))->assertRedirect();

    expect((float) $try->fresh()->rate)->toBe(40.0)
        ->and((float) $eur->fresh()->rate)->toBe(1.0);
});
