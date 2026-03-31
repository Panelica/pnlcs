<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\Client;

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
            'status' => 'Pending',
        ]));
    }

    public function renewDomain(Domain $domain, int $years = 1): Domain
    {
        $domain->update([
            'expiry_date' => $domain->expiry_date->addYears($years),
            'next_due_date' => $domain->expiry_date->addYears($years),
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
        $domain->update(['status' => 'Cancelled']);
        return $domain->fresh();
    }
}
