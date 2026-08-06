<?php

namespace App\Services;

use App\Enums\DomainStatus;
use App\Models\Client;
use App\Models\Domain;
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
    /**
     * The registrar would not renew: leave the dates alone and raise it.
     */
    private function reportRenewalRefused(Domain $domain, string $reason): void
    {
        Log::warning('DomainService::renewDomain — registrar refused; dates left as they are', [
            'domain' => $domain->id, 'registrar' => $domain->registrar, 'message' => $reason,
        ]);

        try {
            app(NotificationService::class)->dispatch('domain.renew_failed', [
                'event_type' => 'domain.renew_failed',
                'subject' => 'Domain renewal refused by the registrar',
                'message' => "The registrar refused to renew {$domain->domain}: {$reason}. "
                    .'The expiry date has been left as it is; the domain still needs renewing.',
                'domain_id' => $domain->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('DomainService::renewDomain — could not raise the refusal: '.$e->getMessage());
        }
    }

    public function renewDomain(Domain $domain, int $years = 1): Domain
    {
        $registrar = app(ModuleRegistry::class)->getRegistrarModule((string) $domain->registrar);

        if ($registrar) {
            // r122-renew: a refusal is not a renewal. The dates used to be
            // advanced anyway, so the panel showed a domain paid up until next
            // year while the registry had it expiring this month - no reminder
            // would go out, because nothing was due, and the domain lapsed with
            // the customer's site on it. The dates now stay where they are and
            // somebody is told, because only a person can fix a locked domain
            // or an empty registrar balance.
            try {
                $result = $registrar->renew($domain, $years);

                if ($result['success'] ?? false) {
                    return $domain->fresh();
                }

                $this->reportRenewalRefused($domain, (string) ($result['message'] ?? 'no reason given'));
            } catch (\Throwable $e) {
                $this->reportRenewalRefused($domain, $e->getMessage());
            }

            return $domain->fresh();
        }

        // No registrar module or API failure: advance billing dates from the
        // current expiry. copy() avoids mutating the shared Carbon instance
        // (the previous code added the interval twice → next_due 1 period ahead).
        $base = $domain->expiry_date ? $domain->expiry_date->copy() : now();
        $newExpiry = $base->addYears($years);

        $domain->update([
            'expiry_date' => $newExpiry,
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
