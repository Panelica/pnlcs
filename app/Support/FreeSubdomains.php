<?php

namespace App\Support;

use App\Enums\ServiceStatus;
use App\Models\Domain;
use App\Models\Service;
use App\Models\Setting;
use Throwable;

/**
 * Free subdomains: a hosting order can take a name under one of the shop's own
 * domains (kafem.example-sites.com) instead of a domain of its own, the way
 * WHMCS's "Free Subdomains" do.
 *
 * Set under Setup > General > Domains: the parent domains offered, and the
 * names no customer may take under them (www, mail, the shop's own hosts).
 * The same rules apply when a customer types such a name as their own domain,
 * so the order form is not the only way in.
 */
class FreeSubdomains
{
    /** Names kept back when the operator has not listed their own. */
    public const DEFAULT_DISALLOWED = [
        'www', 'mail', 'email', 'webmail', 'smtp', 'imap', 'pop', 'pop3', 'mx', 'ftp', 'sftp',
        'ns', 'ns1', 'ns2', 'ns3', 'ns4', 'dns', 'autoconfig', 'autodiscover', 'webdisk',
        'admin', 'administrator', 'root', 'panel', 'cpanel', 'whm', 'plesk', 'billing', 'client', 'clients',
        'api', 'app', 'cdn', 'static', 'assets', 'status', 'support', 'help', 'secure', 'ssl', 'vpn',
        'test', 'dev', 'staging', 'localhost', 'server', 'host',
    ];

    /** @return list<string> the parent domains offered, lowercased, without a leading dot */
    public static function parents(): array
    {
        $out = [];
        foreach (self::lines(self::setting('FreeSubdomains')) as $line) {
            $name = ltrim($line, '.');
            if (self::isHostname($name) && str_contains($name, '.')) {
                $out[] = $name;
            }
        }

        return array_values(array_unique($out));
    }

    public static function enabled(): bool
    {
        return self::parents() !== [];
    }

    /** @return list<string> the first labels no customer may take */
    public static function disallowed(): array
    {
        $own = array_values(array_filter(self::lines(self::setting('DisallowedSubdomainPrefixes')),
            fn ($l) => preg_match('/^[a-z0-9-]+$/', $l)));

        return $own !== [] ? $own : self::DEFAULT_DISALLOWED;
    }

    /** The parent a full name falls under, or null when it is not one of ours. */
    public static function parentOf(string $domain): ?string
    {
        $domain = strtolower(trim($domain));
        foreach (self::parents() as $parent) {
            if ($domain === $parent || str_ends_with($domain, '.'.$parent)) {
                return $parent;
            }
        }

        return null;
    }

    /**
     * Why a name under one of the shop's domains cannot be ordered, or null
     * when it can. $domain is the full name (kafem.example-sites.com).
     */
    public static function problem(string $domain): ?string
    {
        $domain = strtolower(trim($domain));
        $parent = self::parentOf($domain);
        if ($parent === null) {
            return __('client.cart.subdomain_parent_invalid');
        }

        $label = $domain === $parent ? '' : substr($domain, 0, -strlen('.'.$parent));
        // One label only: a.b.example-sites.com would sit under someone else's a.
        if ($label === '' || str_contains($label, '.') || ! preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/', $label)) {
            return __('client.cart.subdomain_invalid');
        }
        if (in_array($label, self::disallowed(), true)) {
            return __('client.cart.subdomain_reserved', ['name' => $domain]);
        }
        if (self::taken($domain)) {
            return __('client.cart.subdomain_taken', ['name' => $domain]);
        }

        return null;
    }

    /** Whether a live service or a domain record already has the name. */
    public static function taken(string $domain): bool
    {
        $gone = [ServiceStatus::Terminated->value, ServiceStatus::Cancelled->value, ServiceStatus::Fraud->value];

        return Service::where('domain', $domain)->whereNotIn('status', $gone)->exists()
            || Domain::where('domain', $domain)->exists();
    }

    private static function isHostname(string $name): bool
    {
        return (bool) preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/', $name);
    }

    /** @return list<string> */
    private static function lines(string $value): array
    {
        return array_values(array_filter(array_map(fn ($l) => strtolower(trim($l)), preg_split('/[\s,]+/', $value) ?: [])));
    }

    private static function setting(string $key): string
    {
        try {
            return (string) Setting::get($key, '');
        } catch (Throwable) {
            return '';
        }
    }
}
