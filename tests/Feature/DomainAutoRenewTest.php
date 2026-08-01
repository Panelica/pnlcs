<?php

use App\Models\Client;
use App\Models\Domain;
use App\Models\User;

/**
 * What the panel says about a domain renewing, and what actually happens.
 *
 * The domains table has no auto_renew column. Both customer screens read
 * $domain->auto_renew anyway, so every domain was shown as not renewing -
 * including the ones the invoice generator was about to bill for. The switch
 * that decides it is payment_method: anything but "none" renews, which is what
 * the billing query uses and says so in a comment.
 *
 * There was no way to change it either. The route and the controller method
 * exist; nothing in any view pointed at them.
 */
function domainOwner(?string $paymentMethod = 'banktransfer'): array
{
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);

    $domain = Domain::create([
        'client_id' => $client->id,
        'domain' => 'renewing-example.com',
        'registrar' => 'manual',
        'status' => 'active',
        'payment_method' => $paymentMethod,
        'registration_date' => now()->subYear(),
        'expiry_date' => now()->addMonth(),
        'next_due_date' => now()->addMonth(),
        'recurring_amount' => 12,
        'registration_period' => 1,
    ]);

    return [$user, $domain];
}

test('a domain that will renew says so', function () {
    [$user, $domain] = domainOwner();

    expect($domain->auto_renew)->toBeTrue();

    $this->actingAs($user)->get(route('client.domains.show', $domain))
        ->assertOk()
        ->assertSee(__('client.status.enabled'), false);
});

test('a domain that will not renew says that instead', function () {
    [$user, $domain] = domainOwner('none');

    expect($domain->auto_renew)->toBeFalse();

    $this->actingAs($user)->get(route('client.domains.show', $domain))
        ->assertOk()
        ->assertSee(__('client.status.disabled'), false);
});

test('the customer is given a way to change it', function () {
    [$user, $domain] = domainOwner();

    $this->actingAs($user)->get(route('client.domains.show', $domain))
        ->assertOk()
        ->assertSee(route('client.domains.autorenew', $domain), false);
});

test('turning it off stops the renewal', function () {
    [$user, $domain] = domainOwner();

    $this->actingAs($user)->post(route('client.domains.autorenew', $domain))->assertRedirect();

    expect($domain->fresh()->auto_renew)->toBeFalse();

    // And the billing query agrees: this domain is no longer due to be billed.
    $due = Domain::where('id', $domain->id)
        ->where(fn ($q) => $q->whereNull('payment_method')->orWhere('payment_method', '!=', 'none'))
        ->exists();

    expect($due)->toBeFalse();
});

test('what the list shows is what the biller will do', function () {
    [$user, $renewing] = domainOwner();
    [, $stopped] = domainOwner('none');

    $html = $this->actingAs($user)->get(route('client.domains.index'))->assertOk()->getContent();

    expect($renewing->auto_renew)->toBeTrue()
        ->and($stopped->auto_renew)->toBeFalse()
        ->and($html)->toContain(__('client.domains.yes'));
});
