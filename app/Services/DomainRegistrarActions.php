<?php

namespace App\Services;

use App\Contracts\ManagesChildNameservers;
use App\Contracts\ManagesDomainContacts;
use App\Contracts\ManagesWhoisPrivacy;
use App\Contracts\RegistrarModuleInterface;
use App\Models\Domain;
use App\Models\DomainPricing;
use App\Services\Module\ModuleRegistry;
use Illuminate\Support\Facades\Log;

/**
 * Registrar actions on one domain that the client area and staff both offer:
 * WHOIS privacy, the WHOIS contact and glue records. Each is available only
 * when the domain is active and its registrar module has the capability.
 * The callers check who may act; this class does the work and records it.
 */
class DomainRegistrarActions
{
    public function registrar(Domain $domain): ?RegistrarModuleInterface
    {
        if (! filled($domain->registrar)) {
            return null;
        }

        return app(ModuleRegistry::class)->getRegistrarModule((string) $domain->registrar);
    }

    public function privacyModule(Domain $domain): ?ManagesWhoisPrivacy
    {
        $module = $this->registrar($domain);

        return $module instanceof ManagesWhoisPrivacy && $this->isActive($domain) ? $module : null;
    }

    public function contactsModule(Domain $domain): ?ManagesDomainContacts
    {
        $module = $this->registrar($domain);

        return $module instanceof ManagesDomainContacts && $this->isActive($domain) ? $module : null;
    }

    public function glueModule(Domain $domain): ?ManagesChildNameservers
    {
        $module = $this->registrar($domain);

        return $module instanceof ManagesChildNameservers && $this->isActive($domain) ? $module : null;
    }

    /** The extension's yearly WHOIS privacy price; 0 when free or not offered. */
    public function privacyPrice(Domain $domain): float
    {
        $price = DomainPricing::where('extension', $this->extensionOf($domain))->value('privacy_price');

        return $price === null ? 0.0 : (float) $price;
    }

    /**
     * Switch WHOIS privacy at the registrar, and record it once the registrar
     * has done it. Turning paid privacy off stops charging for it at renewal,
     * never below the extension's own renewal price for the term.
     *
     * @return array{success: bool, message: string}
     */
    public function setPrivacy(Domain $domain, bool $enable): array
    {
        $module = $this->privacyModule($domain);
        if (! $module) {
            return ['success' => false, 'message' => ''];
        }

        try {
            $result = $module->setPrivacy($domain, $enable);
        } catch (\Throwable $e) {
            Log::error("WHOIS privacy change failed for {$domain->domain}: {$e->getMessage()}");
            $result = ['success' => false, 'message' => ''];
        }

        if (! ($result['success'] ?? false)) {
            return ['success' => false, 'message' => (string) ($result['message'] ?? '')];
        }

        $changes = ['id_protection' => $enable];
        $price = $this->privacyPrice($domain);
        if (! $enable && $price > 0) {
            $years = max(1, (int) ($domain->registration_period ?: 1));
            $pricing = DomainPricing::where('extension', $this->extensionOf($domain))->first();
            $floor = round((float) ($pricing?->renew_price ?? 0) * $years, 2);
            $changes['recurring_amount'] = max($floor, round((float) $domain->recurring_amount - $price * $years, 2));
        }
        $domain->update($changes);

        return ['success' => true, 'message' => ''];
    }

    /**
     * The WHOIS contact the registry holds, or the client's profile when it
     * cannot be read (fromProfile tells the form to say so).
     *
     * @return array{contact: array<string, mixed>, fromProfile: bool}
     */
    public function contact(Domain $domain): array
    {
        $contact = null;
        if ($module = $this->contactsModule($domain)) {
            try {
                $contact = $module->getContact($domain);
            } catch (\Throwable $e) {
                Log::warning("WHOIS contact lookup failed for {$domain->domain}: {$e->getMessage()}");
            }
        }

        if ($contact !== null) {
            return ['contact' => $contact, 'fromProfile' => false];
        }

        $client = $domain->client;

        return ['contact' => [
            'first_name' => $client?->first_name, 'last_name' => $client?->last_name, 'company_name' => $client?->company_name,
            'email' => $client?->email, 'phone' => $client?->phone_number, 'address1' => $client?->address1,
            'city' => $client?->city, 'state' => $client?->state, 'postcode' => $client?->postcode, 'country' => $client?->country,
        ], 'fromProfile' => true];
    }

    /** What a WHOIS contact must have before it is sent to the registrar. */
    public static function contactRules(): array
    {
        return [
            'first_name' => 'required|string|max:80',
            'last_name' => 'required|string|max:80',
            'company_name' => 'nullable|string|max:256',
            'email' => 'required|email|max:256',
            'phone' => ['required', 'string', 'max:24', 'regex:/^\+?[0-9 ().-]{6,24}$/'],
            'address1' => 'required|string|max:256',
            'city' => 'required|string|max:80',
            'state' => 'nullable|string|max:80',
            'postcode' => 'required|string|max:15',
            'country' => ['required', 'string', \Illuminate\Validation\Rule::in(array_keys(\App\Support\Countries::all()))],
        ];
    }

    /** @return array{success: bool, message: string} */
    public function saveContact(Domain $domain, array $contact): array
    {
        $module = $this->contactsModule($domain);
        if (! $module) {
            return ['success' => false, 'message' => ''];
        }

        try {
            $result = $module->saveContact($domain, $contact);
        } catch (\Throwable $e) {
            Log::error("WHOIS contact update failed for {$domain->domain}: {$e->getMessage()}");
            $result = ['success' => false, 'message' => ''];
        }

        return ['success' => (bool) ($result['success'] ?? false), 'message' => (string) ($result['message'] ?? '')];
    }

    /**
     * The glue records registered under the domain; null when the registry
     * could not be read.
     *
     * @return list<array{host: string, ips: list<string>}>|null
     */
    public function glueHosts(Domain $domain): ?array
    {
        $module = $this->glueModule($domain);
        if (! $module) {
            return null;
        }

        try {
            return $module->getChildNameservers($domain);
        } catch (\Throwable $e) {
            Log::warning("Glue lookup failed for {$domain->domain}: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * ns1 or ns1.example.com, read as a name under this domain; anything
     * else (another domain, a bare dot) is refused, since a registry only
     * takes glue under the domain itself.
     */
    public function glueHost(Domain $domain, string $input): ?string
    {
        $name = strtolower(rtrim(trim($input), '.'));
        $suffix = '.'.strtolower($domain->domain);
        if (! str_ends_with($name, $suffix)) {
            $name .= $suffix;
        }
        $label = substr($name, 0, -strlen($suffix));

        return preg_match('/^(?!-)[a-z0-9-]{1,63}(?<!-)(\.(?!-)[a-z0-9-]{1,63}(?<!-))*$/', $label) ? $name : null;
    }

    /**
     * Add a glue record, or change the addresses of one that exists.
     *
     * @param  list<string>  $ips
     * @return array{success: bool, message: string}
     */
    public function saveGlue(Domain $domain, string $host, array $ips): array
    {
        $module = $this->glueModule($domain);
        if (! $module) {
            return ['success' => false, 'message' => ''];
        }

        try {
            $existing = collect($module->getChildNameservers($domain) ?? [])->pluck('host')->all();
            $result = $module->saveChildNameserver($domain, $host, array_values(array_filter($ips)), in_array($host, $existing, true));
        } catch (\Throwable $e) {
            Log::error("Glue save failed for {$host}: {$e->getMessage()}");
            $result = ['success' => false, 'message' => ''];
        }

        return ['success' => (bool) ($result['success'] ?? false), 'message' => (string) ($result['message'] ?? '')];
    }

    /** @return array{success: bool, message: string} */
    public function deleteGlue(Domain $domain, string $host): array
    {
        $module = $this->glueModule($domain);
        if (! $module) {
            return ['success' => false, 'message' => ''];
        }

        try {
            $result = $module->deleteChildNameserver($domain, $host);
        } catch (\Throwable $e) {
            Log::error("Glue delete failed for {$host}: {$e->getMessage()}");
            $result = ['success' => false, 'message' => ''];
        }

        return ['success' => (bool) ($result['success'] ?? false), 'message' => (string) ($result['message'] ?? '')];
    }

    private function isActive(Domain $domain): bool
    {
        return strtolower((string) $domain->status) === 'active';
    }

    private function extensionOf(Domain $domain): string
    {
        return '.'.implode('.', array_slice(explode('.', strtolower((string) $domain->domain)), 1));
    }
}
