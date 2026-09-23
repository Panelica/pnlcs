<?php

namespace App\Services;

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
        $tldKey = substr($domain, strpos($domain, '.') + 1);

        $result = $this->checkWithRegistrar($domain)
            ?? app(WhoisLookup::class)->check($domain, self::WHOIS_SERVERS[$tldKey] ?? null);

        return [
            'domain' => $domain,
            'available' => (bool) $result['available'],
            'checked' => (bool) $result['checked'],
        ];
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
        $name = (string) Setting::get('default_registrar', 'domainnameapi');
        if ($name === '' || $name === 'manual') {
            return null;
        }

        try {
            $module = app(ModuleRegistry::class)->getRegistrarModule($name);
            if (! $module) {
                return null;
            }

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
