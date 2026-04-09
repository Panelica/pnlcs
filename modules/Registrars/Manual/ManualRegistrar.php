<?php

namespace Modules\Registrars\Manual;

use App\Contracts\RegistrarModuleInterface;
use App\Models\Domain;

/**
 * Manual Registrar Module
 *
 * For domains managed manually (no API integration).
 * Register/transfer/renew operations update the domain record locally.
 */
class ManualRegistrar implements RegistrarModuleInterface
{
    public function getModuleName(): string
    {
        return "Manual";
    }

    public function getConfigFields(): array
    {
        return []; // No API credentials needed
    }

    /**
     * Register a domain manually — marks it as Active immediately.
     */
    public function register(Domain $domain, int $years, array $params = []): array
    {
        $domain->update([
            "status"              => "active",
            "registrar"           => "Manual",
            "registration_period" => $years,
            "registration_date"   => now(),
            "expiry_date"         => now()->addYears($years),
            "next_due_date"       => now()->addYears($years),
        ]);

        return [
            "success" => true,
            "message" => "Domain registered manually.",
            "domain"  => $domain->domain,
        ];
    }

    /**
     * Transfer a domain manually — marks as Transfer Pending.
     */
    public function transfer(Domain $domain, string $eppCode): array
    {
        $domain->update([
            "status"    => "pending_transfer",
            "registrar" => "Manual",
        ]);

        return [
            "success" => true,
            "message" => "Transfer initiated. Please complete manually at the registrar.",
            "domain"  => $domain->domain,
        ];
    }

    /**
     * Renew a domain manually — extends expiry date.
     */
    public function renew(Domain $domain, int $years): array
    {
        $currentExpiry = $domain->expiry_date ?? now();
        $newExpiry     = $currentExpiry->addYears($years);

        $domain->update([
            "expiry_date"   => $newExpiry,
            "next_due_date" => $newExpiry,
            "status"        => "active",
        ]);

        return [
            "success"    => true,
            "message"    => "Domain renewed for {$years} year(s).",
            "expiry_date" => $newExpiry->toDateString(),
        ];
    }

    public function getNameservers(Domain $domain): array
    {
        $ns = json_decode($domain->nameservers ?? "[]", true);
        return is_array($ns) ? $ns : [];
    }

    public function saveNameservers(Domain $domain, array $nameservers): bool
    {
        $domain->update(["nameservers" => json_encode($nameservers)]);
        return true;
    }

    public function getEPPCode(Domain $domain): string
    {
        // Manual registrar does not store EPP codes automatically
        return "(Please retrieve EPP code from your registrar manually)";
    }

    public function getLockStatus(Domain $domain): bool
    {
        // Default to locked for security
        return true;
    }

    public function toggleLock(Domain $domain, bool $lock): bool
    {
        // Manual operation — just return success
        return true;
    }

    /**
     * Check availability via WHOIS lookup.
     */
    public function checkAvailability(string $domain): array
    {
        $parts = explode(".", $domain, 2);
        if (count($parts) < 2) {
            return ["available" => false, "domain" => $domain, "error" => "Invalid domain"];
        }

        $tld    = $parts[1];
        $result = $this->queryWhois($domain, $tld);

        return [
            "available" => $result["available"],
            "domain"    => $domain,
            "method"    => "whois",
        ];
    }

    protected function queryWhois(string $domain, string $tld): array
    {
        $servers = [
            "com" => "whois.verisign-grs.com",
            "net" => "whois.verisign-grs.com",
            "org" => "whois.pir.org",
            "io"  => "whois.nic.io",
            "dev" => "whois.nic.google",
            "app" => "whois.nic.google",
        ];

        $server = $servers[$tld] ?? null;
        if (!$server) {
            return ["available" => true, "error" => true];
        }

        $conn = @fsockopen($server, 43, $errno, $errstr, 5);
        if (!$conn) {
            return ["available" => true, "error" => true];
        }

        fwrite($conn, $domain . "\r\n");
        $response = "";
        $deadline = microtime(true) + 5;
        while (!feof($conn) && microtime(true) < $deadline) {
            $response .= fgets($conn, 1024);
        }
        fclose($conn);

        $availPhrases = ["No match for", "NOT FOUND", "No Data Found", "Status: free", "No entries found", "not found"];
        foreach ($availPhrases as $phrase) {
            if (stripos($response, $phrase) !== false) {
                return ["available" => true, "error" => false];
            }
        }

        return ["available" => false, "error" => false];
    }
}
