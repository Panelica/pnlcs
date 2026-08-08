<?php

use App\Models\Client;
use App\Models\Domain;
use App\Widgets\DomainsWidget;

/**
 * The renewals widget leaving out the renewals that matter most.
 *
 * The widget is titled "Upcoming renewals" and its tile says "Expiring (30d)",
 * but it counted active domains whose expiry falls in the next thirty days and
 * nothing else. A domain in grace has already expired and is in the window
 * where it can still be renewed at the ordinary price - the invoice generator
 * says so in as many words, billing domains that are active or in grace - so it
 * is the most urgent renewal there is, and it appeared nowhere: its status is
 * not active, and its expiry date is in the past, so it failed both halves of
 * the query.
 *
 * An operator watching this widget for what to chase saw everything except the
 * domains about to be lost.
 */
function renewalDomain(string $status, string $expiry): Domain
{
    return Domain::create([
        'client_id' => Client::factory()->create()->id,
        'domain' => uniqid('renew-').'.test',
        'type' => 'Register',
        'registration_period' => 1,
        'registration_date' => today()->subYear(),
        'expiry_date' => $expiry,
        'next_due_date' => $expiry,
        'status' => $status,
        'recurring_amount' => 12.99,
        'registrar' => 'manual',
    ]);
}

it('counts a domain already in grace among the renewals', function () {
    renewalDomain('grace', today()->subDays(3)->toDateString());

    expect((new DomainsWidget)->getData()['expiring'])->toBe(1);
});

it('lists the domain in grace, soonest first', function () {
    renewalDomain('grace', today()->subDays(3)->toDateString());
    renewalDomain('active', today()->addDays(10)->toDateString());

    $upcoming = (new DomainsWidget)->getData()['upcoming'];

    expect($upcoming)->toHaveCount(2)
        ->and($upcoming[0]['domain'])->toContain('renew-');
});

it('still counts an active domain expiring inside the window', function () {
    renewalDomain('active', today()->addDays(10)->toDateString());

    expect((new DomainsWidget)->getData()['expiring'])->toBe(1);
});

it('still leaves a domain expiring months away out of it', function () {
    renewalDomain('active', today()->addDays(90)->toDateString());

    expect((new DomainsWidget)->getData()['expiring'])->toBe(0);
});

it('leaves out a domain nobody can renew any more', function () {
    renewalDomain('cancelled', today()->subDays(3)->toDateString());
    renewalDomain('transferred_away', today()->subDays(3)->toDateString());
    renewalDomain('redemption', today()->subDays(40)->toDateString());

    expect((new DomainsWidget)->getData()['expiring'])->toBe(0);
});
