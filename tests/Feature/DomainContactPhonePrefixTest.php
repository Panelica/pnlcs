<?php

use App\Models\Client;
use App\Models\Domain;
use App\Models\RegistrarSettings;
use Illuminate\Support\Facades\Http;
use Modules\Registrars\DomainNameApi\DomainNameApiRegistrar;

/*
 * The registrant's phone reaches the registry with the dialling code the
 * customer picked.
 *
 * Registration and the profile ask for a dialling code next to the number
 * (clients.phone_prefix), but the DomainNameAPI contact took the code from the
 * billing country instead: a customer with a German address and a Turkish
 * mobile was registered as +49.
 */

function dcpSettings(): void
{
    RegistrarSettings::updateOrCreate(['registrar' => 'domainnameapi', 'setting' => 'reseller_id'], ['value' => 'reseller-1']);
    RegistrarSettings::updateOrCreate(['registrar' => 'domainnameapi', 'setting' => 'api_key'], ['value' => 'key-1']);
}

function dcpRegister(array $client): array
{
    dcpSettings();
    Http::fake(['*domains/register-with-contacts*' => Http::response(['success' => true])]);

    $domain = Domain::create([
        'client_id' => Client::factory()->create(array_merge(['country' => 'DE'], $client))->id,
        'domain' => 'phone-example.com', 'type' => 'Register', 'registrar' => 'DomainNameAPI',
        'status' => 'pending', 'registration_period' => 1,
        'first_payment_amount' => 10, 'recurring_amount' => 10,
    ]);

    (new DomainNameApiRegistrar)->register($domain, 1);

    $sent = null;
    Http::assertSent(function ($request) use (&$sent) {
        $sent = $request->data()['contacts'][0] ?? null;

        return str_contains($request->url(), 'register-with-contacts');
    });

    return $sent;
}

it('sends the dialling code the customer picked', function () {
    $contact = dcpRegister(['phone_prefix' => '+90', 'phone_number' => '0532 111 22 33']);

    expect($contact['PhoneCountryCode'])->toBe('90')
        ->and($contact['Phone'])->toBe('5321112233');
});

it('falls back to the billing country when no code was picked', function () {
    $contact = dcpRegister(['phone_prefix' => null, 'phone_number' => '0151 2345 6789']);

    expect($contact['PhoneCountryCode'])->toBe('49')
        ->and($contact['Phone'])->toBe('15123456789');
});

it('still reads a number written with its own international code', function () {
    $contact = dcpRegister(['phone_prefix' => '+90', 'phone_number' => '+44 20 7946 0000']);

    expect($contact['PhoneCountryCode'])->toBe('44');
});
