<?php

namespace App\Services;

use App\Enums\DomainStatus;
use App\Models\Domain;
use App\Models\Client;
use App\Services\Module\ModuleRegistry;
use Illuminate\Support\Facades\Log;

class DomainService
{
    public function registerDomain(Client $client, array $data): Domain
    {
        return Domain::create(array_merge($data, [
            'client_id' => $client->id,
            'type' => 'Register',
            'registration_date' => now(),
            'expiry_date' => now()->addYears($data['registration_period'] ?? 1),
            'next_due_date' => now()->addYears($data['registration_period'] ?? 1),
            'status' => DomainStatus::Pending->value,
        ]));
    }

    /**
     * Renew a domain for the given number of years. When a registrar module is
     * configured it performs the real renewal API call (the module advances the
     * expiry/next_due dates itself on success). If there is no module or the API
     * call fails, billing dates are still advanced locally so the paid renewal
     * moves forward — the customer has already paid.
     */
    public function renewDomain(Domain $domain, int $years = 1): Domain
    {
        $registrar = app(ModuleRegistry::class)->getRegistrarModule((string) $domain->registrar);

        if ($registrar) {
            try {
                $result = $registrar->renew($domain, $years);
                if ($result['success'] ?? false) {
                    return $domain->fresh();
                }
                Log::warning('DomainService::renewDomain — registrar renew failed, advancing dates locally', [
                    'domain' => $domain->id, 'registrar' => $domain->registrar, 'message' => $result['message'] ?? null,
                ]);
            } catch (\Throwable $e) {
                Log::error('DomainService::renewDomain — registrar renew threw, advancing dates locally: ' . $e->getMessage(), [
                    'domain' => $domain->id, 'registrar' => $domain->registrar,
                ]);
            }
        }

        // No registrar module or API failure: advance billing dates from the
        // current expiry. copy() avoids mutating the shared Carbon instance
        // (the previous code added the interval twice → next_due 1 period ahead).
        $base      = $domain->expiry_date ? $domain->expiry_date->copy() : now();
        $newExpiry = $base->addYears($years);

        $domain->update([
            'expiry_date'   => $newExpiry,
            'next_due_date' => $newExpiry->copy(),
        ]);

        return $domain->fresh();
    }

    public function updateNameservers(Domain $domain, array $nameservers): Domain
    {
        $domain->update(['nameservers' => json_encode($nameservers)]);
        return $domain->fresh();
    }

    public function cancelDomain(Domain $domain): Domain
    {
        $domain->update(['status' => DomainStatus::Cancelled->value]);
        return $domain->fresh();
    }
}
