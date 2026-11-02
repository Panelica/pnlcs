<?php

use App\Models\Domain;
use App\Services\DomainService;
use App\Services\Module\ModuleRegistry;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * eNom numbers its nameserver parameters NS1, NS2 and so on. The client's form
 * and the hosting attach both hand the service an array keyed ns1, ns2, so the
 * numbers cannot come from the keys.
 */
function enomDomain(): Domain
{
    return Domain::factory()->create([
        'domain' => 'enom-nameservers.com',
        'registrar' => 'Enom',
        'status' => 'active',
    ]);
}

test('nameservers keyed by name reach eNom numbered in order', function () {
    Http::fake(['*' => Http::response('<?xml version="1.0"?><interface-response><ErrCount>0</ErrCount></interface-response>', 200)]);

    $result = app(DomainService::class)->updateNameservers(enomDomain(), [
        'ns1' => 'ns1.example.net',
        'ns2' => 'ns2.example.net',
    ]);

    expect($result['success'])->toBeTrue();

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'command=SetDNS')
        && $request['NS1'] === 'ns1.example.net'
        && $request['NS2'] === 'ns2.example.net');
});

test('a gap in the keys leaves no gap in the numbers', function () {
    Http::fake(['*' => Http::response('<?xml version="1.0"?><interface-response><ErrCount>0</ErrCount></interface-response>', 200)]);

    $saved = app(ModuleRegistry::class)->getRegistrarModule('Enom')->saveNameservers(enomDomain(), [
        0 => 'ns1.example.net',
        2 => 'ns3.example.net',
    ]);

    expect($saved)->toBeTrue();

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'command=SetDNS')
        && $request['NS1'] === 'ns1.example.net'
        && $request['NS2'] === 'ns3.example.net'
        && ! isset($request['NS3']));
});
