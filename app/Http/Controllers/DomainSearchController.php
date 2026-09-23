<?php

namespace App\Http\Controllers;

use App\Models\DomainPricing;
use Illuminate\Http\Request;

class DomainSearchController extends Controller
{


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
        // "available". DomainAvailability is the one place that decides; the
        // API's domainwhois asks it too.
        $whoisResult = app(\App\Services\DomainAvailability::class)->check($fullDomain);

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
