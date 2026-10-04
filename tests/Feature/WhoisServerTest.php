<?php

use App\Services\DomainAvailability;
use App\Services\WhoisLookup;

/*
 * The WHOIS fallback looked a name's server up by everything after the first
 * dot, so every second-level name - ornek.com.tr, example.co.uk,
 * example.com.au - found no server and came back "could not check". And .tr
 * pointed at whois.nic.tr, which no longer answers: TRABIS runs .tr now.
 */

test('a second-level name is asked of its registry, and .tr of TRABIS', function () {
    expect(DomainAvailability::whoisServerFor('ornek.com.tr'))->toBe('whois.trabis.gov.tr')
        ->and(DomainAvailability::whoisServerFor('ornek.tr'))->toBe('whois.trabis.gov.tr')
        ->and(DomainAvailability::whoisServerFor('example.co.uk'))->toBe('whois.nic.uk')
        ->and(DomainAvailability::whoisServerFor('example.com.au'))->toBe('whois.auda.org.au')
        ->and(DomainAvailability::whoisServerFor('Example.COM'))->toBe('whois.verisign-grs.com')
        ->and(DomainAvailability::whoisServerFor('example.unknownzz'))->toBeNull();
});

test('a free .com.tr is reported free when TRABIS says so', function () {
    $this->mock(WhoisLookup::class, function ($m) {
        $m->shouldReceive('check')->once()->with('bos-alan-adi.com.tr', 'whois.trabis.gov.tr')
            ->andReturn(['available' => true, 'checked' => true, 'response' => 'No match found for bos-alan-adi.com.tr']);
    });

    $method = new ReflectionMethod(DomainAvailability::class, 'checkWithWhois');
    $result = $method->invoke(app(DomainAvailability::class), 'bos-alan-adi.com.tr');

    expect($result)->checked->toBeTrue()->available->toBeTrue();
});

test('the phrases TRABIS and Nominet use for a free name are recognised', function () {
    $lookup = new ReflectionClass(WhoisLookup::class);
    $phrases = $lookup->getConstant('AVAILABLE_PHRASES');

    expect(collect($phrases)->contains(fn ($p) => str_contains('No match found for x.com.tr', $p)))->toBeTrue()
        ->and(collect($phrases)->contains(fn ($p) => str_contains('No match for "x.co.uk".', $p)))->toBeTrue();
});
