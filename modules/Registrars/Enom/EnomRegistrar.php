<?php

namespace Modules\Registrars\Enom;

use App\Contracts\RegistrarModuleInterface;
use App\Models\Domain;
use Illuminate\Support\Facades\Log;

/**
 * eNom Registrar Module
 *
 * Integrates with the eNom reseller API.
 * Test: https://resellertest.enom.com/interface.asp
 * Live: https://reseller.enom.com/interface.asp
 *
 * Docs: https://www.enom.com/APICommandCatalog/
 */
class EnomRegistrar implements RegistrarModuleInterface
{
    protected string $apiUrl;
    protected string $uid;
    protected string $pw;

    public function __construct()
    {
        $settings    = $this->loadSettings();
        $testMode    = ($settings["test_mode"] ?? "1") === "1";
        $this->apiUrl = $testMode
            ? "https://resellertest.enom.com/interface.asp"
            : "https://reseller.enom.com/interface.asp";
        $this->uid   = $settings["uid"] ?? "";
        $this->pw    = $settings["pw"] ?? "";
    }

    public function getModuleName(): string
    {
        return "Enom";
    }

    public function getConfigFields(): array
    {
        return [
            ["name" => "uid",       "label" => "eNom Username (UID)",   "type" => "text",     "required" => true],
            ["name" => "pw",        "label" => "eNom Password",          "type" => "password", "required" => true],
            ["name" => "test_mode", "label" => "Test Mode",              "type" => "yesno",    "default" => "1"],
        ];
    }

    public function register(Domain $domain, int $years, array $params = []): array
    {
        $parts   = $this->splitDomain($domain->domain);
        $response = $this->call("Purchase", [
            "SLD"        => $parts["sld"],
            "TLD"        => $parts["tld"],
            "NumYears"   => $years,
            "NS1"        => $params["ns1"] ?? "",
            "NS2"        => $params["ns2"] ?? "",
            "RegistrantFirstName"  => $params["firstname"] ?? "Admin",
            "RegistrantLastName"   => $params["lastname"]  ?? "Admin",
            "RegistrantEmailAddress" => $params["email"]   ?? "",
            "RegistrantPhone"      => $params["phone"]     ?? "+1.5555555555",
            "RegistrantAddress1"   => $params["address"]   ?? "123 Main St",
            "RegistrantCity"       => $params["city"]      ?? "Anytown",
            "RegistrantStateProvinceChoice" => "S",
            "RegistrantStateProvince" => $params["state"]  ?? "CA",
            "RegistrantPostalCode" => $params["postcode"]  ?? "90210",
            "RegistrantCountry"    => $params["country"]   ?? "US",
        ]);

        if (($response["ErrCount"] ?? "1") === "0") {
            $domain->update([
                "status"            => "active",
                "registrar"         => "Enom",
                "registration_date" => now(),
                "expiry_date"       => now()->addYears($years),
                "next_due_date"     => now()->addYears($years),
            ]);
            return ["success" => true, "message" => "Domain registered successfully via eNom."];
        }

        $error = $response["Err1"] ?? "Unknown eNom error";
        Log::error("Enom register failed for {$domain->domain}: {$error}");
        return ["success" => false, "message" => $error];
    }

    public function transfer(Domain $domain, string $eppCode): array
    {
        $parts    = $this->splitDomain($domain->domain);
        $response = $this->call("TP_CreateOrder", [
            "SLD"      => $parts["sld"],
            "TLD"      => $parts["tld"],
            "AuthInfo1" => $eppCode,
        ]);

        if (($response["ErrCount"] ?? "1") === "0") {
            $domain->update(["status" => "pending_transfer", "registrar" => "Enom"]);
            return ["success" => true, "message" => "Transfer order created at eNom."];
        }

        $error = $response["Err1"] ?? "Unknown eNom error";
        return ["success" => false, "message" => $error];
    }

    public function renew(Domain $domain, int $years): array
    {
        $parts    = $this->splitDomain($domain->domain);
        $response = $this->call("Extend", [
            "SLD"      => $parts["sld"],
            "TLD"      => $parts["tld"],
            "NumYears" => $years,
        ]);

        if (($response["ErrCount"] ?? "1") === "0") {
            $newExpiry = ($domain->expiry_date ?? now())->addYears($years);
            $domain->update([
                "expiry_date"   => $newExpiry,
                "next_due_date" => $newExpiry,
            ]);
            return ["success" => true, "message" => "Domain renewed for {$years} year(s).", "expiry_date" => $newExpiry->toDateString()];
        }

        $error = $response["Err1"] ?? "Unknown eNom error";
        return ["success" => false, "message" => $error];
    }

    public function getNameservers(Domain $domain): array
    {
        $parts    = $this->splitDomain($domain->domain);
        $response = $this->call("GetDNS", [
            "SLD" => $parts["sld"],
            "TLD" => $parts["tld"],
        ]);

        $ns = [];
        for ($i = 1; $i <= 12; $i++) {
            if (!empty($response["NS" . $i])) {
                $ns[] = strtolower($response["NS" . $i]);
            }
        }

        return $ns ?: json_decode($domain->nameservers ?? "[]", true);
    }

    public function saveNameservers(Domain $domain, array $nameservers): bool
    {
        $parts  = $this->splitDomain($domain->domain);
        $params = [
            "SLD"    => $parts["sld"],
            "TLD"    => $parts["tld"],
            "NSAction" => "REPLACE",
        ];

        foreach ($nameservers as $i => $ns) {
            $params["NS" . ($i + 1)] = $ns;
        }

        $response = $this->call("SetDNS", $params);

        if (($response["ErrCount"] ?? "1") === "0") {
            $domain->update(["nameservers" => json_encode($nameservers)]);
            return true;
        }

        return false;
    }

    public function getEPPCode(Domain $domain): string
    {
        $parts    = $this->splitDomain($domain->domain);
        $response = $this->call("GetDomainInfo", [
            "SLD" => $parts["sld"],
            "TLD" => $parts["tld"],
        ]);

        return $response["auth-info"] ?? $response["AuthCode"] ?? "(Not available)";
    }

    public function getLockStatus(Domain $domain): bool
    {
        $parts    = $this->splitDomain($domain->domain);
        $response = $this->call("GetDomainInfo", [
            "SLD" => $parts["sld"],
            "TLD" => $parts["tld"],
        ]);

        $lockStatus = strtolower($response["lockstatus"] ?? "locked");
        return $lockStatus !== "unlocked";
    }

    public function toggleLock(Domain $domain, bool $lock): bool
    {
        $parts    = $this->splitDomain($domain->domain);
        $response = $this->call("SetRegLock", [
            "SLD"        => $parts["sld"],
            "TLD"        => $parts["tld"],
            "UnlockRegistrar" => $lock ? "0" : "1",
        ]);

        return ($response["ErrCount"] ?? "1") === "0";
    }

    public function checkAvailability(string $domain): array
    {
        $parts    = $this->splitDomain($domain);
        $response = $this->call("Check", [
            "SLD" => $parts["sld"],
            "TLD" => $parts["tld"],
        ]);

        // RRPCode 210 = available, 211 = taken
        $available = ($response["RRPCode"] ?? "211") === "210";

        return [
            "available" => $available,
            "domain"    => $domain,
            "method"    => "enom_api",
        ];
    }

    protected function call(string $command, array $params = []): array
    {
        $params = array_merge([
            "command"     => $command,
            "uid"         => $this->uid,
            "pw"          => $this->pw,
            "responsetype" => "xml",
        ], $params);

        $url = $this->apiUrl . "?" . http_build_query($params);

        $ctx = stream_context_create([
            "http" => [
                "timeout" => 15,
                "method"  => "GET",
            ],
            "ssl" => [
                "verify_peer"      => true,
                "verify_peer_name" => true,
            ],
        ]);

        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) {
            return ["ErrCount" => "1", "Err1" => "Could not connect to eNom API"];
        }

        return $this->parseXml($raw);
    }

    protected function parseXml(string $xml): array
    {
        $result = [];
        try {
            $obj = simplexml_load_string($xml);
            if ($obj === false) {
                return ["ErrCount" => "1", "Err1" => "Invalid XML response from eNom"];
            }
            $result = json_decode(json_encode($obj), true);
        } catch (\Throwable $e) {
            return ["ErrCount" => "1", "Err1" => "XML parse error: " . $e->getMessage()];
        }
        return $result;
    }

    protected function splitDomain(string $domain): array
    {
        $parts = explode(".", $domain, 2);
        return [
            "sld" => $parts[0] ?? "",
            "tld" => $parts[1] ?? "",
        ];
    }

    protected function loadSettings(): array
    {
        try {
            $rows = \App\Models\RegistrarSettings::where("registrar", "enom")->get();
            $settings = [];
            foreach ($rows as $row) {
                $settings[$row->setting] = $row->value;
            }
            return $settings;
        } catch (\Throwable $e) {
            return [];
        }
    }
}
