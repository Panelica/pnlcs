<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class WhoisController extends Controller
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

    public function index()
    {
        return view("admin.whois", ["result" => null, "domain" => null, "server" => null]);
    }

    public function lookup(Request $request)
    {
        $request->validate(["domain" => "required|string|max:253"]);

        $domain = trim(strtolower($request->domain));
        // Strip http/https/www
        $domain = preg_replace("#^https?://(www\\.)?#", "", $domain);
        $domain = rtrim($domain, "/");

        $parts = explode(".", $domain);
        if (count($parts) < 2) {
            return back()->withErrors(["domain" => "Invalid domain name."]);
        }

        // Determine TLD (could be 2-level like .co.uk)
        $tldKey      = null;
        $whoisServer = null;

        // Try 2-level TLD first (e.g. co.uk)
        if (count($parts) >= 3) {
            $tryTld = $parts[count($parts) - 2] . "." . $parts[count($parts) - 1];
            if (isset($this->whoisServers[$tryTld])) {
                $tldKey      = $tryTld;
                $whoisServer = $this->whoisServers[$tryTld];
            }
        }
        // Fall back to single TLD
        if (!$whoisServer) {
            $tryTld = $parts[count($parts) - 1];
            $tldKey      = $tryTld;
            $whoisServer = $this->whoisServers[$tryTld] ?? null;
        }

        if (!$whoisServer) {
            $result = [
                "raw"      => "No WHOIS server known for ." . $tldKey . ". Try querying whois.iana.org manually.",
                "domain"   => $domain,
                "server"   => "unknown",
                "parsed"   => [],
                "error"    => true,
            ];
            return view("admin.whois", ["result" => $result, "domain" => $domain, "server" => "unknown"]);
        }

        $raw    = $this->doWhoisLookup($domain, $whoisServer);
        $parsed = $this->parseWhois($raw);

        $result = [
            "raw"    => $raw ?: "(No response received)",
            "domain" => $domain,
            "server" => $whoisServer,
            "parsed" => $parsed,
            "error"  => false,
        ];

        return view("admin.whois", ["result" => $result, "domain" => $domain, "server" => $whoisServer]);
    }

    protected function doWhoisLookup(string $domain, string $server): string
    {
        $conn = @fsockopen($server, 43, $errno, $errstr, 8);
        if (!$conn) {
            return "Error: Could not connect to " . $server . " (errno=" . $errno . ": " . $errstr . ")";
        }

        fwrite($conn, $domain . "\r\n");
        $response = "";
        $deadline = microtime(true) + 8;
        while (!feof($conn) && microtime(true) < $deadline) {
            $chunk = fread($conn, 4096);
            if ($chunk === false) break;
            $response .= $chunk;
        }
        fclose($conn);

        return $response;
    }

    protected function parseWhois(string $raw): array
    {
        $fields = [
            "Registrar"         => ["Registrar:", "Registrar Name:"],
            "Registrar URL"     => ["Registrar URL:"],
            "Registrant Name"   => ["Registrant Name:", "Registrant Organization:"],
            "Creation Date"     => ["Creation Date:", "Created:", "Registration Date:", "Domain Registration Date:"],
            "Expiry Date"       => ["Registry Expiry Date:", "Expiration Date:", "Paid-till:", "Expiry Date:", "Domain Expiration Date:"],
            "Updated Date"      => ["Updated Date:", "Last Updated:", "Last update:"],
            "Name Servers"      => ["Name Server:", "Nserver:"],
            "Status"            => ["Domain Status:", "Status:"],
            "DNSSEC"            => ["DNSSEC:"],
            "Registrar IANA ID" => ["Registrar IANA ID:"],
        ];

        $parsed = [];
        foreach ($fields as $label => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match_all("/^" . preg_quote($pattern, "/") . "\s*(.+)$/mi", $raw, $matches)) {
                    $values = array_map("trim", $matches[1]);
                    $parsed[$label] = count($values) === 1 ? $values[0] : $values;
                    break;
                }
            }
        }

        return $parsed;
    }
}
