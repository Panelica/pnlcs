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
            $this->recordSearch($results, $fullDomain);
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
        $this->recordSearch($results, $sld . $tld);

        return response()->json($results);
    }

    /**
     * The name the visitor asked about, not the suggestions shown with it,
     * and the account when someone is signed in. Nothing else (no IP).
     */
    private function recordSearch(?array $results, string $asked): void
    {
        $primary = $results['primary'] ?? null;
        $available = is_array($primary) && ! empty($primary['checked']) ? (bool) $primary['available'] : null;

        $clientId = null;
        if ($user = auth()->user()) {
            $selected = session('active_client_id');
            $clientId = ($selected ? $user->clients()->whereKey($selected)->value('clients.id') : null) ?? $user->clients()->value('clients.id');
        }

        \App\Models\WhoisLog::record($asked, $available, $clientId ? (int) $clientId : null, 'search');
    }

    public function pricing()
    {
        $popular  = DomainPricing::where("enabled", true)->orderBy("sort_order")->get();
        return view("client.domain-pricing", compact("popular"));
    }

    protected function checkDomainWithAlternatives(string $sld, string $tld, $allTlds): array
    {
        // Suggest alternatives: the first endings from the operator's list
        // that are sold, besides the one searched for. The list and its
        // length are settings (Admin > Settings > Domain search); the
        // defaults are what was hardcoded here, so nothing changes until an
        // operator says otherwise - a .com.tr seller could not suggest .com.tr.
        $alternativeTlds = [];
        $limit = $this->suggestionCount();
        foreach ($this->suggestionTlds() as $altTld) {
            if (count($alternativeTlds) >= $limit) {
                break;
            }
            if ($altTld !== $tld && $allTlds->firstWhere("extension", $altTld)) {
                $alternativeTlds[] = $altTld;
            }
        }

        // Ask about every name in one go. Asked one after another, a search
        // with six suggestions waited for seven registry round trips - about
        // 21 seconds against DomainNameAPI.
        $askTlds = $allTlds->firstWhere("extension", $tld) ? array_merge([$tld], $alternativeTlds) : $alternativeTlds;
        $names = array_map(fn ($t) => $sld . $t, $askTlds);
        $lookups = app(\App\Services\DomainAvailability::class)->checkMany($names);

        $primary = $this->checkSingleDomain($sld, $tld, $allTlds, $lookups);
        $alternatives = [];
        foreach ($alternativeTlds as $altTld) {
            $alternatives[] = $this->checkSingleDomain($sld, $altTld, $allTlds, $lookups);
        }

        return [
            "primary"      => $primary,
            "alternatives" => $alternatives,
            "sld"          => $sld,
            "tld"          => $tld,
        ];
    }

    /**
     * @param  array<string, array{domain: string, available: bool, checked: bool}>  $lookups
     */
    /** The endings the operator wants suggested, in order, each with its leading dot. */
    public const DEFAULT_SUGGESTIONS = '.com .net .org .io .co .dev .app .online .site .xyz';

    /** @return array<int, string> */
    protected function suggestionTlds(): array
    {
        $raw = (string) \App\Models\Setting::get('DomainSuggestionTlds', '');
        $raw = trim($raw) === '' ? self::DEFAULT_SUGGESTIONS : $raw;

        $tlds = [];
        foreach (preg_split('/[\s,]+/', strtolower($raw), -1, PREG_SPLIT_NO_EMPTY) as $tld) {
            $tlds[] = '.'.ltrim($tld, '.');
        }

        return array_values(array_unique($tlds));
    }

    protected function suggestionCount(): int
    {
        $count = (int) \App\Models\Setting::get('DomainSuggestionCount', 6);

        return $count > 0 ? min($count, 12) : 6;
    }

    protected function checkSingleDomain(string $sld, string $tld, $allTlds, array $lookups = []): ?array
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
        $whoisResult = $lookups[strtolower($fullDomain)]
            ?? app(\App\Services\DomainAvailability::class)->check($fullDomain);

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
