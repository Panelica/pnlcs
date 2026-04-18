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

    protected function checkSingleDomain(string $sld, string $tld, $allTlds): ?array
    {
        $fullDomain = $sld . $tld;
        $tldKey     = ltrim($tld, ".");

        // Find pricing
        $pricing = $allTlds->firstWhere("extension", $tld);
        if (!$pricing) {
            return null;
        }

        // Check availability via WHOIS
        $whoisServer = $this->whoisServers[$tldKey] ?? null;
        $available   = true;
        $whoisError  = false;

        if ($whoisServer) {
            $whoisResult = $this->queryWhois($fullDomain, $whoisServer);
            $available   = $whoisResult["available"];
            $whoisError  = $whoisResult["error"];
        }

        return [
            "domain"      => $fullDomain,
            "tld"         => $tld,
            "sld"         => $sld,
            "available"   => $available,
            "whois_error" => $whoisError,
            "price"       => $pricing->register_price,
            "renew_price" => $pricing->renew_price,
            "transfer_price" => $pricing->transfer_price,
        ];
    }

    protected function queryWhois(string $domain, string $server): array
    {
        $conn = @fsockopen($server, 43, $errno, $errstr, 5);
        if (!$conn) {
            // Cannot reach WHOIS — assume available
            return ["available" => true, "error" => true, "response" => ""];
        }

        fwrite($conn, $domain . "\r\n");
        $response = "";
        $timeout  = microtime(true) + 5;
        while (!feof($conn) && microtime(true) < $timeout) {
            $response .= fgets($conn, 1024);
        }
        fclose($conn);

        $availablePhrases = [
            "No match for",
            "NOT FOUND",
            "No Data Found",
            "Status: free",
            "No entries found",
            "not found",
            "is free",
            "No match",
            "Object not found",
            "No information available",
            "Domain not found",
            "Available",
            "This domain is available",
        ];

        foreach ($availablePhrases as $phrase) {
            if (stripos($response, $phrase) !== false) {
                return ["available" => true, "error" => false, "response" => $response];
            }
        }

        // If we got data back but no "not found" phrase, domain is likely registered
        return ["available" => false, "error" => false, "response" => $response];
    }

    public function rawWhois(Request $request)
    {
        $request->validate(["domain" => "required|string|max:253"]);

        $domain = trim(strtolower($request->domain));
        $parts  = explode(".", $domain);
        if (count($parts) < 2) {
            return response()->json(["error" => "Invalid domain"]);
        }

        $tldKey      = implode(".", array_slice($parts, 1));
        $whoisServer = $this->whoisServers[$tldKey] ?? null;

        if (!$whoisServer) {
            return response()->json(["error" => "No WHOIS server known for ." . $tldKey, "response" => ""]);
        }

        $result = $this->queryWhois($domain, $whoisServer);
        return response()->json([
            "domain"     => $domain,
            "server"     => $whoisServer,
            "available"  => $result["available"],
            "response"   => $result["response"],
        ]);
    }
}
