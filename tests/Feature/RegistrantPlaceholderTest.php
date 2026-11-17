<?php

use App\Models\Client;
use App\Models\Domain;
use App\Services\Module\ModuleRegistry;
use App\Support\RegistrantContact;
use Illuminate\Support\Facades\Http;

/*
 * eNom, Namecheap, ResellerClub and Openprovider filled a missing registrant
 * detail with a placeholder ("Admin", "123 Main St", "Anytown", CA 90210,
 * +1.5555555555) and registered the domain under it (#452). The registration
 * now stops and names what is missing, before anything is sent.
 */

function placeholderDomain(string $registrar, array $client = []): Domain
{
    $owner = Client::factory()->create(array_merge([
        'first_name' => 'Ayse', 'last_name' => 'Yilmaz', 'email' => 'ayse@example.com',
        'phone_number' => '+90.5321234567', 'address1' => 'Bagdat Cad. 1', 'city' => 'Istanbul',
        'state' => 'Istanbul', 'postcode' => '34000', 'country' => 'TR',
    ], $client));

    return Domain::factory()->create([
        'client_id' => $owner->id,
        'domain' => strtolower($registrar).'-contact.com',
        'registrar' => $registrar,
        'status' => 'pending',
    ]);
}

/** The params core builds from the client (OrderService::registerOrderedDomain). */
function registrantParams(Domain $domain): array
{
    $c = $domain->client;

    return array_filter([
        'firstname' => $c->first_name, 'lastname' => $c->last_name, 'email' => $c->email,
        'phone' => $c->phone_number, 'address' => $c->address1, 'city' => $c->city,
        'state' => $c->state, 'postcode' => $c->postcode, 'country' => $c->country,
    ]);
}

test('a registrant with a missing detail is refused before anything is sent', function (string $registrar) {
    Http::fake();
    $domain = placeholderDomain($registrar, ['phone_number' => null, 'address1' => null]);

    $result = app(ModuleRegistry::class)->getRegistrarModule($registrar)->register($domain, 1, registrantParams($domain));

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('phone')
        ->and($result['message'])->toContain('address');
    Http::assertNothingSent();
})->with(['Enom', 'Namecheap', 'ResellerClub', 'OpenProvider']);

test('no placeholder is left in the modules', function () {
    foreach (['Enom/EnomRegistrar', 'Namecheap/NamecheapRegistrar', 'ResellerClub/ResellerClubRegistrar', 'OpenProvider/OpenProviderRegistrar'] as $file) {
        $source = file_get_contents(base_path("modules/Registrars/{$file}.php"));

        expect($source)->not->toContain('123 Main St')
            ->and($source)->not->toContain('Anytown')
            ->and($source)->not->toContain('90210')
            ->and($source)->not->toContain('5555555555');
    }
});

test('the check only asks for what every country has', function () {
    expect(RegistrantContact::missing([
        'firstname' => 'A', 'lastname' => 'B', 'email' => 'a@b.c', 'phone' => '+1.2',
        'address' => 'x', 'city' => 'y', 'country' => 'HK',
    ]))->toBe([]);
});
