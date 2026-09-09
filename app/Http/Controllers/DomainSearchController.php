<?php

namespace App\Http\Controllers;

use App\Models\DomainPricing;
use Illuminate\Http\Request;

class DomainSearchController extends Controller
{
    protected array $whoisServers = [
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

    public function index(Request $request)
    {
        $tlds = DomainPricing::where("enabled", true)->orderBy("sort_order")->get();
        $results = null;
        $searchDomain = null;

        if ($request->has("domain") && $request->domain) {
            $rawDomain = trim(strtolower($request->domain));
            $tldParam  = trim(strtolower($request->get("tld", ".com")));

            // If domain already contains a dot, split it
            if (str_contains($rawDomain, ".")) {
                $parts = explode(".", $rawDomain, 2);
                $sld   = $parts[0];
                $tld   = "." . $parts[1];
            } else {
                $sld = $rawDomain;
                $tld = $tldParam;
            }

            $fullDomain = $sld . $tld;
            $searchDomain = $fullDomain;
            $results = $this->checkDomainWithAlternatives($sld, $tld, $tlds);
        }

        return view("client.domain-search", compact("tlds", "results", "searchDomain"));
    }

    public function check(Request $request)
    {
        $request->validate(["domain" => "required|string|max:253"]);

        $rawDomain = trim(strtolower($request->domain));
        $tldParam  = trim(strtolower($request->get("tld", ".com")));

        if (str_contains($rawDomain, ".")) {
            $parts = explode(".", $rawDomain, 2);
            $sld   = $parts[0];
            $tld   = "." . $parts[1];
        } else {
            $sld = $rawDomain;
            $tld = $tldParam;
        }

        $tlds = DomainPricing::where("enabled", true)->orderBy("sort_order")->get();
        $results = $this->checkDomainWithAlternatives($sld, $tld, $tlds);

        return response()->json($results);
    }

    public function pricing()
    {
        $popular  = DomainPricing::where("enabled", true)->orderBy("sort_order")->get();
        return view("client.domain-pricing", compact("popular"));
    }

    protected function checkDomainWithAlternatives(string $sld, string $tld, $allTlds): array
    {
        // Check primary domain
        $primary = $this->checkSingleDomain($sld, $tld, $allTlds);

        // Suggest alternatives
        $suggestionTlds = [".com", ".net", ".org", ".io", ".co", ".dev", ".app", ".online", ".site", ".xyz"];
        $alternatives   = [];
        foreach ($suggestionTlds as $altTld) {
            if ($altTld === $tld) {
                continue;
            }
            $result = $this->checkSingleDomain($sld, $altTld, $allTlds);
            if ($result !== null) {
                $alternatives[] = $result;
            }
            if (count($alternatives) >= 6) {
                break;
            }
        }

        return [
            "primary"      => $primary,
            "alternatives" => $alternatives,
            "sld"          => $sld,
            "tld"          => $tld,
        ];
    }

    /**
     * Ask the configured registrar module whether a domain is free.
     *
     * Returns null when there is no module, it cannot answer, or the call
     * fails - the caller then falls back to WHOIS. A null is "we do not know",
     * never "it is available".
     */
    protected function checkWithRegistrar(string $domain): ?array
    {
        $name = (string) \App\Models\Setting::get('default_registrar', 'domainnameapi');
        if ($name === '' || $name === 'manual') {
            return null;
        }

        try {
            $module = app(\App\Services\Module\ModuleRegistry::class)->getRegistrarModule($name);
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
            \Illuminate\Support\Facades\Log::warning('Registrar availability check failed; falling back to WHOIS', [
                'domain'  => $domain,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function checkSingleDomain(string $sld, string $tld, $allTlds): ?array
    {
        $fullDomain = $sld . $tld;
        $tldKey     = ltrim($tld, ".");

        // Find pricing
        $pricing = $allTlds->firstWhere("extension", $tld);
        if (!$pricing) {
            return null;
        }

        // Check availability via WHOIS. An unanswered lookup is not an answer:
        // it used to be read as "available", so a registry being unreachable
        // put a price and an add-to-cart button next to a name nobody had
        // checked - and the customer paid for a registration that then failed.
        // The registrar answers from the registry itself, so it covers TLDs
        // that run no port-43 WHOIS at all (.dev and .app among them) and the
        // .tr family, whose availability WHOIS reports unreliably. WHOIS stays
        // as the fallback for when no registrar module is configured or the
        // API is unreachable - an unanswered lookup must still not read as
        // "available".
        $whoisResult = $this->checkWithRegistrar($fullDomain);

        if ($whoisResult === null) {
            $whoisResult = app(\App\Services\WhoisLookup::class)
                ->check($fullDomain, $this->whoisServers[$tldKey] ?? null);
        }

        return [
            "domain"      => $fullDomain,
            "tld"         => $tld,
            "sld"         => $sld,
            "available"   => $whoisResult["available"],
            "checked"     => $whoisResult["checked"],
            "whois_error" => ! $whoisResult["checked"],
            "price"       => $pricing->register_price,
            "renew_price" => $pricing->renew_price,
            "transfer_price" => $pricing->transfer_price,
            "restore_price" => $pricing->restore_price,
            "grace_period" => $pricing->grace_period,
        ];
    }
}
