<?php

use App\Models\DomainPricing;
use App\Models\Setting;
use App\Services\DomainAvailability;
use App\Services\RdapAvailability;
use App\Services\WhoisLookup;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/*
 * When the registrar did not answer, the registry was asked on port 43 and its
 * free text was matched against a short list of phrases. Anything else counted
 * as "taken": a free .eu ("Status: AVAILABLE"), a free .online ("is available
 * for registration"), a rate-limit notice, and the "TLD is not supported"
 * answer of a server that no longer serves the TLD. The domain search showed
 * those names as unavailable, intermittently, whenever the registrar left
 * them out of its answer. Many listed gTLD servers no longer run port 43 at
 * all; their registries answer over RDAP.
 */

beforeEach(function () {
    Cache::forget('rdap.dns-bootstrap');
});

function rdapBootstrap(array $services = [[['com', 'net'], ['https://rdap.example-registry.test/']]]): array
{
    return ['version' => '1.0', 'publication' => '2026-11-20T23:00:03Z', 'services' => $services];
}

/** Real port-43 answers, trimmed to their first lines. */
dataset('registry answers', [
    // Verisign
    '.com free' => ['qzxvkplmwr7tn.com', "No match for \"QZXVKPLMWR7TN.COM\".\n>>> Last update of whois database: 2026-11-22T18:33:43Z <<<", 'free'],
    '.com taken' => ['google.com', "   Domain Name: GOOGLE.COM\n   Registry Domain ID: 2138514_DOMAIN_COM-VRSN\n   Registrar WHOIS Server: whois.markmonitor.com\n   Creation Date: 1997-09-15T04:00:00Z", 'taken'],
    // Radix
    '.online free' => ['qzxvkplmwr7tn.online', ">>> Domain qzxvkplmwr7tn.online is available for registration\n>>> Please visit https://rdap.radix.host/registrars/ for a list of accredited\nregistrars", 'free'],
    '.online taken' => ['google.online', "Domain Name: google.online\nRegistry Domain ID: D9566483-CNIC\nRegistrar WHOIS Server: whois.markmonitor.com", 'taken'],
    // CentralNic
    '.co free' => ['qzxvkplmwr7tn.co', "The queried object does not exist: DOMAIN NOT FOUND\n>>> Last update of WHOIS database: 2026-11-22T18:33:51.0Z <<<", 'free'],
    // EURid
    '.eu free' => ['qzxvkplmwr7tn.eu', "% The WHOIS service offered by EURid and the access to the records\n% in the EURid WHOIS database are provided for information purposes\n\nDomain: qzxvkplmwr7tn.eu\nScript: LATIN\n\nStatus: AVAILABLE", 'free'],
    '.eu taken' => ['google.eu', "% The WHOIS service offered by EURid and the access to the records\n\nDomain: google.eu\nScript: LATIN\n\nRegistrant:\n        NOT DISCLOSED!\n        Visit www.eurid.eu for the web-based WHOIS.\n\nRegistrar:\n        Name: Markmonitor Inc.", 'taken'],
    // DNS Belgium (a tab after "Status:")
    '.be free' => ['qzxvkplmwr7tn.be', "Domain:\tqzxvkplmwr7tn.be\nStatus:\tAVAILABLE", 'free'],
    '.be taken' => ['google.be', "Domain:\tgoogle.be\nStatus:\tNOT AVAILABLE\nRegistered:\tTue Dec 12 2000", 'taken'],
    // nic.it
    '.it free' => ['qzxvkplmwr7tn.it', "Domain:             qzxvkplmwr7tn.it\nStatus:             AVAILABLE", 'free'],
    '.it taken' => ['google.it', "Domain:             google.it\nStatus:             ok\nSigned:             no\nCreated:            1999-12-10 00:00:00", 'taken'],
    // nic.at
    '.at free' => ['qzxvkplmwr7tn.at', "% Copyright (c)2026 by NIC.AT (1)\n%\n% Restricted rights.\n\n% nothing found", 'free'],
    '.at taken' => ['google.at', "% Copyright (c)2026 by NIC.AT (1)\n\ndomain:            google.at\nregistrar:         MarkMonitor Inc. ( https://nic.at/registrar/434 )\nregistrant:        GL11783559-NICAT", 'taken'],
    // DENIC
    '.de free' => ['qzxvkplmwr7tn.de', "Domain: qzxvkplmwr7tn.de\nStatus: free", 'free'],
    '.de taken' => ['google.de', "Domain: google.de\nStatus: connect", 'taken'],
    // Nominet (the name on the line after "Domain name:")
    '.co.uk free' => ['qzxvkplmwr7tn.co.uk', "    No match for \"qzxvkplmwr7tn.co.uk\".\n\n    This domain name has not been registered.", 'free'],
    '.uk taken' => ['google.uk', "    Domain name:\n        google.uk\n\n    Registrar:\n        Markmonitor Inc. [Tag = MARKMONITOR]", 'taken'],
    // TRABIS
    '.com.tr free' => ['qzxvkplmwr7tn.com.tr', 'No match found for qzxvkplmwr7tn.com.tr', 'free'],
    '.tr taken' => ['google.tr', "** Domain Name: google.tr\nDomain Status: Active\nFrozen Status: -\nTransfer Status: The domain is LOCKED to transfer.", 'taken'],
    // AFNIC
    '.fr free' => ['qzxvkplmwr7tn.fr', "%% This is the AFNIC Whois server.\n%%\n%% NOT FOUND\n%%", 'free'],
    '.fr taken' => ['google.fr', "%% This is the AFNIC Whois server.\n\ndomain:                        google.fr\nstatus:                        ACTIVE", 'taken'],
    // NASK
    '.pl free' => ['qzxvkplmwr7tn.pl', 'No information available about domain name qzxvkplmwr7tn.pl in the Registry NASK database.', 'free'],
    '.pl taken' => ['google.pl', "DOMAIN NAME:                    google.pl\nregistrant type:                organization\nnameservers:                    ns1.google.com.", 'taken'],
    // Punktum
    '.dk free' => ['qzxvkplmwr7tn.dk', "# Copyright (c) 2002 - 2026 by Punktum dk A/S\n\nNo entries found for the selected source.", 'free'],
    '.dk taken' => ['google.dk', "Domain:               google.dk\nDNS:                  google.dk\nRegistered:           1999-01-10\nRegistrar:            MarkMonitor Inc.", 'taken'],
    // Internetstiftelsen
    '.se free' => ['qzxvkplmwr7tn.se', 'domain "qzxvkplmwr7tn.se" not found.', 'free'],
    '.se taken' => ['google.se', "state:            active\ndomain:           google.se\nholder:           mmr8008-171440\ncreated:          2003-08-27", 'taken'],
    // IEDR, CIRA
    '.ie free' => ['qzxvkplmwr7tn.ie', "Not found: qzxvkplmwr7tn.ie\n% Important Notice", 'free'],
    '.ca free' => ['qzxvkplmwr7tn.ca', 'Not found: qzxvkplmwr7tn.ca', 'free'],
    // ROTLD
    '.ro free' => ['qzxvkplmwr7tn.ro', "% The WHOIS service offered by ROTLD and the access to the records in the ROTLD WHOIS database\n\n% No entries found for the selected source(s).", 'free'],
    '.ro taken' => ['google.ro', "  Domain Name: google.ro\n  Registered On: 2000-07-17\n  Registrar: MarkMonitor Inc.", 'taken'],
    // DNS-LU, free and rate-limited
    '.lu free' => ['qzxvkplmwr7tn.lu', "% WHOIS qzxvkplmwr7tn.lu\n% No such domain", 'free'],
    'DNS-LU rate limit' => ['google.lu', "% WHOIS google.lu\n%% Maximum query rate reached", 'unknown'],
    'nic.ch refuses automated clients' => ['example.ch', 'Requests of this client are not permitted. Please use https://www.nic.ch/whois/ for queries.', 'unknown'],
    'SIDN rate limit' => ['example.nl', 'Error: ratelimit exceeded', 'unknown'],
    'a server that does not serve the TLD' => ['example.info', 'TLD is not supported.', 'unknown'],
    'an empty answer' => ['example.com', '', 'unknown'],
]);

it('reads a port-43 answer as free, taken or not an answer', function (string $domain, string $response, string $expected) {
    $result = (new WhoisLookup)->read($domain, $response);

    $got = ! $result['checked'] ? 'unknown' : ($result['available'] ? 'free' : 'taken');

    expect($got)->toBe($expected);
})->with('registry answers');

it('asks the registry RDAP server from the IANA bootstrap file: 404 is free, a domain object is taken', function () {
    Http::fake([
        'data.iana.org/*' => Http::response(rdapBootstrap()),
        'rdap.example-registry.test/domain/free-name.com' => Http::response(['errorCode' => 404], 404),
        'rdap.example-registry.test/domain/taken-name.com' => Http::response(['objectClassName' => 'domain', 'ldhName' => 'TAKEN-NAME.COM']),
    ]);

    $rdap = app(RdapAvailability::class);

    expect($rdap->check('free-name.com'))->toBe(['available' => true, 'checked' => true])
        ->and($rdap->check('Taken-Name.com'))->toBe(['available' => false, 'checked' => true]);

    // The bootstrap file is downloaded once and kept.
    Http::assertSentCount(3);
});

it('leaves a refused, rate-limited or odd RDAP answer unanswered, never taken', function (int $status, array $body) {
    Http::fake([
        'data.iana.org/*' => Http::response(rdapBootstrap()),
        'rdap.example-registry.test/*' => Http::response($body, $status),
    ]);

    expect(app(RdapAvailability::class)->check('example.com'))->toBeNull();
})->with([
    'rate limited' => [429, []],
    'refused' => [403, []],
    'server error' => [500, []],
    'not a domain object' => [200, ['objectClassName' => 'entity']],
]);

it('does not take a 404 that is not JSON for the registry saying the name is free', function () {
    Http::fake([
        'data.iana.org/*' => Http::response(rdapBootstrap()),
        'rdap.example-registry.test/*' => Http::response('<html><body>Not Found</body></html>', 404, ['Content-Type' => 'text/html']),
    ]);

    expect(app(RdapAvailability::class)->check('example.com'))->toBeNull();
});

it('leaves a server it could not reach alone for a while instead of waiting on it for every name', function () {
    Http::fake([
        'data.iana.org/*' => Http::response(rdapBootstrap()),
        'rdap.example-registry.test/*' => Http::failedConnection(),
    ]);

    $rdap = app(RdapAvailability::class);

    expect($rdap->check('first.com'))->toBeNull()
        ->and($rdap->check('second.com'))->toBeNull()
        ->and($rdap->check('third.net'))->toBeNull();

    // The bootstrap file and the one attempt on the registry server.
    Http::assertSentCount(2);
});

it('does not ask about something that is not a host name', function (string $name) {
    Http::fake([
        'data.iana.org/*' => Http::response(rdapBootstrap()),
        '*' => Http::response([], 404),
    ]);

    expect(app(RdapAvailability::class)->check($name))->toBeNull();

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'rdap.example-registry.test'));
})->with(['a/b.com', 'a?b.com', '-name.com', 'na me.com', 'com']);

it('serves a second-level name from its TLD server and has no server for a TLD without RDAP', function () {
    Http::fake(['data.iana.org/*' => Http::response(rdapBootstrap([[['uk'], ['https://rdap.nominet.test/uk/']]]))]);

    $rdap = app(RdapAvailability::class);

    expect($rdap->serverFor('example.co.uk'))->toBe('https://rdap.nominet.test/uk/')
        ->and($rdap->serverFor('example.eu'))->toBeNull()
        ->and($rdap->check('example.eu'))->toBeNull();
});

it('prefers the HTTPS server when the bootstrap file lists two', function () {
    Http::fake(['data.iana.org/*' => Http::response(rdapBootstrap([
        [['example'], ['http://rdap.plain.test/', 'https://rdap.secure.test']],
    ]))]);

    expect(app(RdapAvailability::class)->serverFor('name.example'))->toBe('https://rdap.secure.test/');
});

it('asks about an internationalised name in its punycode form', function () {
    Http::fake([
        'data.iana.org/*' => Http::response(rdapBootstrap([[['de'], ['https://rdap.denic.test/']]])),
        'rdap.denic.test/domain/xn--mller-kva.de' => Http::response([], 404),
    ]);

    expect(app(RdapAvailability::class)->check('müller.de'))->toBe(['available' => true, 'checked' => true]);
})->skip(! function_exists('idn_to_ascii'), 'intl is not installed');

it('does without RDAP while IANA cannot be reached, and does not ask again on every search', function () {
    Http::fake(['data.iana.org/*' => Http::response('', 503)]);

    $rdap = app(RdapAvailability::class);

    expect($rdap->check('example.com'))->toBeNull()
        ->and($rdap->check('another.com'))->toBeNull();

    Http::assertSentCount(1);
});

it('shows a free .eu suggestion as available when the registrar left it out (#472)', function () {
    foreach (['.at' => 1, '.eu' => 2] as $extension => $order) {
        DomainPricing::updateOrCreate(['extension' => $extension], [
            'register_price' => 9.99, 'transfer_price' => 9.99, 'renew_price' => 9.99,
            'min_years' => 1, 'max_years' => 10, 'sort_order' => $order, 'enabled' => true,
        ]);
    }
    Setting::set('default_registrar', 'manual');
    Setting::set('DomainSuggestionTlds', '.eu');

    // Neither .at nor .eu has RDAP; both registries answer on port 43.
    Http::fake(['data.iana.org/*' => Http::response(rdapBootstrap())]);
    app()->instance(WhoisLookup::class, new class extends WhoisLookup
    {
        protected function ask(string $server, string $domain): ?string
        {
            return match ($server) {
                'whois.eu' => "% The WHOIS service offered by EURid\n\nDomain: {$domain}\nScript: LATIN\nStatus: AVAILABLE\n",
                'whois.nic.at' => "% Copyright (c)2026 by NIC.AT (1)\n%\n% nothing found\n",
            };
        }
    });

    $results = $this->post(route('client.domain.check'), ['domain' => 'hrbagdskttpppar.at'])->assertOk()->json();

    expect($results['primary'])->available->toBeTrue()->checked->toBeTrue()
        ->and(collect($results['alternatives'])->firstWhere('domain', 'hrbagdskttpppar.eu'))
        ->available->toBeTrue()->checked->toBeTrue();
});

it('the WHOIS servers it lists are the ones IANA names, and .at, .be and .it are among them', function () {
    expect(DomainAvailability::whoisServerFor('example.at'))->toBe('whois.nic.at')
        ->and(DomainAvailability::whoisServerFor('example.be'))->toBe('whois.dns.be')
        ->and(DomainAvailability::whoisServerFor('example.it'))->toBe('whois.nic.it')
        ->and(DomainAvailability::whoisServerFor('example.org'))->toBe('whois.publicinterestregistry.org')
        // No port-43 service any more: RDAP answers for these.
        ->and(DomainAvailability::whoisServerFor('example.dev'))->toBeNull()
        ->and(DomainAvailability::whoisServerFor('example.info'))->toBeNull();
});
