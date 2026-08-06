<?php

use App\Models\Client;
use App\Models\Domain;
use App\Models\RegistrarSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * A registrar nobody has given credentials to.
 *
 * The module takes its username and password from the registrar settings and
 * defaults both to an empty string. With nothing configured it still calls the
 * registrar, which refuses, and the nightly sync writes "eNom lookup failed"
 * against every domain.
 *
 * That is what this installation has been doing every night for a month: nine
 * domains, no registrar settings at all, and a message that sends the operator
 * looking for a network problem instead of telling them what is actually
 * missing.
 */
function domainAtRegistrar(string $registrar = 'enom'): Domain
{
    return Domain::create([
        'client_id' => Client::factory()->create()->id,
        'domain' => 'unconfigured-example.com',
        'type' => 'Register',
        'registration_period' => 1,
        'registration_date' => now()->subMonth(),
        'expiry_date' => now()->addMonths(11),
        'next_due_date' => now()->addMonths(11),
        'status' => 'active',
        'recurring_amount' => 12.99,
        'registrar' => $registrar,
    ]);
}

it('says the registrar has no credentials rather than blaming the lookup', function () {
    Http::fake();

    domainAtRegistrar();

    Log::spy();

    $this->artisan('pnlcs:domain-sync')->assertSuccessful();

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message) => str_contains(strtolower($message), 'not configured'))
        ->atLeast()->once();

    // And it does not call a registrar it has no credentials for.
    Http::assertNothingSent();
});

it('still syncs a registrar that has credentials', function () {
    Http::fake(['*' => Http::response('ErrCount=1&Err1=Domain not found', 200)]);

    RegistrarSettings::updateOrCreate(['registrar' => 'enom', 'setting' => 'uid'], ['value' => 'someuser']);
    RegistrarSettings::updateOrCreate(['registrar' => 'enom', 'setting' => 'pw'], ['value' => 'somepass']);

    domainAtRegistrar();

    $this->artisan('pnlcs:domain-sync')->assertSuccessful();

    Http::assertSentCount(1);
});
