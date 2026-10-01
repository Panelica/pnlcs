<?php

namespace App\Services;

use App\Contracts\ChecksAvailabilityInBulk;
use App\Models\Setting;
use App\Services\Module\ModuleRegistry;
use Illuminate\Support\Facades\Log;

/**
 * Is a domain name free to register?
 *
 * The domain search and the API ask the same question and must get the same
 * answer, so it is answered in one place. The registrar is asked first - it
 * answers from the registry itself, which covers TLDs that run no port-43 WHOIS
 * (.dev, .app) and the .tr family whose WHOIS is unreliable - and WHOIS is the
 * fallback. An unanswered lookup is never "available": checked=false says so.
 */
class DomainAvailability
{
    /** Port-43 WHOIS servers, by the part of the name after the first dot. */
    public const WHOIS_SERVERS = [
        "com"       => "whois.verisign-grs.com",
        "net"       => "whois.verisign-grs.com",
        "org"       => "whois.pir.org",
        "io"        => "whois.nic.io",
        "dev"       => "whois.nic.google",
        "app"       => "whois.nic.google",
        "me"        => "whois.nic.me",
        "co"        => "whois.nic.co",
        "info"      => "whois.afilias.net",
        "biz"       => "whois.biz",
        "xyz"       => "whois.nic.xyz",
        "online"    => "whois.nic.online",
        "site"      => "whois.nic.site",
        "tech"      => "whois.nic.tech",
        "ai"        => "whois.nic.ai",
        "eu"        => "whois.eu",
        "de"        => "whois.denic.de",
        "uk"        => "whois.nic.uk",
        "tr"        => "whois.nic.tr",
        "tv"        => "whois.nic.tv",
        "cc"        => "whois.nic.cc",
        "us"        => "whois.nic.us",
        "in"        => "whois.registry.in",
        "ca"        => "whois.cira.ca",
        "au"        => "whois.auda.org.au",
        "fr"        => "whois.nic.fr",
        "nl"        => "whois.sidn.nl",
        "ru"        => "whois.tcinet.ru",
        "space"     => "whois.nic.space",
        "club"      => "whois.nic.club",
        "store"     => "whois.nic.store",
        "shop"      => "whois.nic.shop",
        "cloud"     => "whois.nic.cloud",
        "host"      => "whois.nic.host",
        "pro"       => "whois.nic.pro",
        "agency"    => "whois.nic.agency",
        "digital"   => "whois.nic.digital",
        "media"     => "whois.nic.media",
        "zone"      => "whois.nic.zone",
        "life"      => "whois.donuts.co",
        "live"      => "whois.donuts.co",
        "world"     => "whois.donuts.co",
        "today"     => "whois.donuts.co",
        "center"    => "whois.donuts.co",
        "network"   => "whois.donuts.co",
        "solutions" => "whois.donuts.co",
        "systems"   => "whois.donuts.co",
        "studio"    => "whois.donuts.co",
        "design"    => "whois.donuts.co",
        "email"     => "whois.donuts.co",
        "pl"        => "whois.dns.pl",
    ];

    /**
     * @return array{domain: string, available: bool, checked: bool}
     */
    public function check(string $domain): array
    {
        $domain = strtolower(trim($domain));

        $result = $this->checkWithRegistrar($domain) ?? $this->checkWithWhois($domain);

        return [
            'domain' => $domain,
            'available' => (bool) $result['available'],
            'checked' => (bool) $result['checked'],
        ];
    }

    /**
     * Several names at once - the domain search asks about the name typed and
     * its suggested endings together.
     *
     * A registrar that can take a list is asked once for all of them; any name
     * it did not answer for goes to WHOIS, as a failed single lookup does. A
     * registrar that cannot is asked one name at a time, exactly as check().
     *
     * @param  array<int, string>  $domains
     * @return array<string, array{domain: string, available: bool, checked: bool}>
     */
    public function checkMany(array $domains): array
    {
        $domains = array_values(array_unique(array_map(fn ($d) => strtolower(trim($d)), $domains)));

        $module = $this->registrarModule();
        if (! $module instanceof ChecksAvailabilityInBulk) {
            $results = [];
            foreach ($domains as $domain) {
                $results[$domain] = $this->check($domain);
            }

            return $results;
        }

        try {
            $answers = $module->checkAvailabilityBulk($domains);
        } catch (\Throwable $e) {
            Log::warning('Registrar bulk availability check failed; falling back to WHOIS', [
                'domains' => $domains,
                'message' => $e->getMessage(),
            ]);
            $answers = [];
        }

        $results = [];
        foreach ($domains as $domain) {
            $result = isset($answers[$domain])
                ? ['available' => (bool) $answers[$domain]['available'], 'checked' => true]
                : $this->checkWithWhois($domain);

            $results[$domain] = [
                'domain' => $domain,
                'available' => (bool) $result['available'],
                'checked' => (bool) $result['checked'],
            ];
        }

        return $results;
    }

    /**
     * @return array{available: bool, checked: bool, response: string}
     */
    private function checkWithWhois(string $domain): array
    {
        $tldKey = substr($domain, strpos($domain, '.') + 1);

        return app(WhoisLookup::class)->check($domain, self::WHOIS_SERVERS[$tldKey] ?? null);
    }

    /** The configured registrar module, or null when there is none to ask. */
    private function registrarModule(): ?object
    {
        $name = (string) Setting::get('default_registrar', 'domainnameapi');
        if ($name === '' || $name === 'manual') {
            return null;
        }

        try {
            return app(ModuleRegistry::class)->getRegistrarModule($name) ?: null;
        } catch (\Throwable $e) {
            Log::warning('Registrar module could not be loaded for an availability check', [
                'registrar' => $name,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Ask the configured registrar module whether a domain is free.
     *
     * Returns null when there is no module, it cannot answer, or the call
     * fails - the caller then falls back to WHOIS. A null is "we do not know",
     * never "it is available".
     */
    private function checkWithRegistrar(string $domain): ?array
    {
        $module = $this->registrarModule();
        if (! $module) {
            return null;
        }

        try {
            $result = $module->checkAvailability($domain);
            if (! empty($result['error'])) {
                return null;
            }

            return [
                'available' => (bool) ($result['available'] ?? false),
                'checked'   => true,
            ];
        } catch (\Throwable $e) {
            Log::warning('Registrar availability check failed; falling back to WHOIS', [
                'domain'  => $domain,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
