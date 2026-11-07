<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Domain;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;

/*
 * Two things the admin domain screen could not do: correct the billing
 * amounts, and remove a domain record that was added by mistake.
 */

function bdAdmin(): Admin
{
    return Admin::factory()->create([
        'role_id' => AdminRole::factory()->fullAdmin()->create()->id,
    ]);
}

function bdDomain(array $attrs = []): Domain
{
    return Domain::create(array_merge([
        'client_id' => Client::factory()->create()->id,
        'type' => 'register',
        'domain' => 'billing.example',
        'registrar' => 'Manual',
        'status' => 'active',
        'first_payment_amount' => 10,
        'recurring_amount' => 20,
    ], $attrs));
}

it('deletes a domain record from the panel', function () {
    $this->withoutMiddleware(VerifyCsrfToken::class);
    $domain = bdDomain();

    $this->actingAs(bdAdmin(), 'admin')
        ->delete(route('admin.domains.destroy', $domain))
        ->assertRedirect(route('admin.domains.index'));

    expect(Domain::find($domain->id))->toBeNull();
});

it('updates the two billing amounts', function () {
    $this->withoutMiddleware(VerifyCsrfToken::class);
    $domain = bdDomain();

    $this->actingAs(bdAdmin(), 'admin')
        ->post(route('admin.domains.billing', $domain), [
            'first_payment_amount' => '12.50',
            'recurring_amount' => '25.00',
        ])->assertRedirect();

    $fresh = $domain->fresh();
    expect((float) $fresh->first_payment_amount)->toBe(12.5)
        ->and((float) $fresh->recurring_amount)->toBe(25.0);
});

it('rejects a negative billing amount', function () {
    $this->withoutMiddleware(VerifyCsrfToken::class);
    $domain = bdDomain();

    $this->actingAs(bdAdmin(), 'admin')
        ->post(route('admin.domains.billing', $domain), [
            'recurring_amount' => '-1',
        ])->assertSessionHasErrors('recurring_amount');

    expect((float) $domain->fresh()->recurring_amount)->toBe(20.0);
});
