<?php

namespace App\Services;

use App\Contracts\ChecksAvailabilityInBulk;
use App\Models\DomainPricing;
use App\Models\Setting;
use App\Services\Module\ModuleRegistry;
use Illuminate\Support\Collection;
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
    /** Cached TLD registrar assignments for this service instance. */
    private ?Collection $registrarPricing = null;

    /**
     * Port-43 WHOIS servers, by the part of the name after the first dot, or by
     * the last label when that longer suffix is not listed (com.tr → tr,
     * co.uk → uk): a registry answers for its second-level names too.
     */
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
        // TRABIS took over .tr from METU; whois.nic.tr no longer answers.
        "tr"        => "whois.trabis.gov.tr",
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
        $domains = array_values(array_unique(
            array_map(fn ($d) => strtolower(trim($d)), $domains)
        ));

        if ($domains === []) {
            return [];
        }

        // Group by the registrar assigned to each TLD.
        $groups = [];
        foreach ($domains as $domain) {
            $groups[$this->registrarName($domain)][] = $domain;
        }

        $results = [];

        foreach ($groups as $registrar => $names) {
            $answers = [];

            $module = $registrar === 'manual'
                ? null
                : $this->registrarModule($names[0]);

            // Preserve bulk checks for capable registrars.
            if ($module instanceof ChecksAvailabilityInBulk) {
                try {
                    $answers = $module->checkAvailabilityBulk($names);
                } catch (\Throwable $e) {
                    Log::warning('Bulk availability failed', [
                        'registrar' => $registrar,
                        'message' => $e->getMessage(),
                    ]);
                }
            }

            foreach ($names as $domain) {
                if (
                    isset($answers[$domain]) &&
                    is_array($answers[$domain]) &&
                    array_key_exists('available', $answers[$domain]) &&
                    empty($answers[$domain]['error'])
                ) {
                    $results[$domain] = [
                        'domain' => $domain,
                        'available' => (bool) $answers[$domain]['available'],
                        'checked' => true,
                    ];
                } elseif ($module instanceof ChecksAvailabilityInBulk) {
                    $whois = $this->checkWithWhois($domain);
                    $results[$domain] = [
                        'domain' => $domain,
                        'available' => (bool) $whois['available'],
                        'checked' => (bool) $whois['checked'],
                    ];
                } else {
                    $results[$domain] = $this->check($domain);
                }
            }
        }

        return $results;
    }

    /**
     * @return array{available: bool, checked: bool, response: string}
     */
    private function checkWithWhois(string $domain): array
    {
        return app(WhoisLookup::class)->check($domain, self::whoisServerFor($domain));
    }

    /** The WHOIS server for a name: its whole suffix if listed, otherwise its top-level label. */
    public static function whoisServerFor(string $domain): ?string
    {
        $domain = strtolower($domain);
        $suffix = substr($domain, strpos($domain, '.') + 1);
        $tld = substr((string) strrchr($domain, '.'), 1);

        return self::WHOIS_SERVERS[$suffix] ?? self::WHOIS_SERVERS[$tld] ?? null;
    }

    /** The configured registrar module, or null when there is none to ask. */
    private function registrarModule(string $domain): ?object
    {
        $name = $this->registrarName($domain);
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
        $module = $this->registrarModule($domain);
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

    private function registrarName(string $domain): string
    {
        $domain = strtolower(trim($domain));

        $this->registrarPricing ??= DomainPricing::where('enabled', true)
            ->get(['extension', 'auto_registrar'])
            ->sortByDesc(fn ($row) => strlen($row->extension));

        // On a label boundary: an extension saved without its leading dot
        // ("com") must not claim every name ending in those letters
        // (example.telecom).
        $pricing = $this->registrarPricing->first(function ($row) use ($domain) {
            $suffix = '.'.ltrim(strtolower(trim((string) $row->extension)), '.');

            return $suffix !== '.' && strlen($domain) > strlen($suffix) && str_ends_with($domain, $suffix);
        });

        $assigned = strtolower(trim((string) ($pricing?->auto_registrar ?? '')));

        return in_array($assigned, ['', 'manual'], true)
            ? strtolower(trim((string) Setting::get('default_registrar', 'domainnameapi')))
            : $assigned;
    }
}
