<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientNote;

class ClientService
{
    public function createClient(array $data): Client
    {
        return Client::create($data);
    }

    public function updateClient(Client $client, array $data): Client
    {
        $client->update($data);
        return $client->fresh();
    }

    public function closeClient(Client $client): Client
    {
        $client->update(['status' => 'closed']);
        $client->services()->where('status', 'Active')->update(['status' => 'Cancelled']);
        return $client->fresh();
    }

    public function addCredit(Client $client, float $amount): Client
    {
        $client->increment('credit', $amount);
        return $client->fresh();
    }

    public function deductCredit(Client $client, float $amount): Client
    {
        $client->update(['credit' => max(0, $client->credit - $amount)]);
        return $client->fresh();
    }

    public function addNote(Client $client, int $adminId, string $note, bool $sticky = false): ClientNote
    {
        return ClientNote::create([
            'client_id' => $client->id,
            'admin_id' => $adminId,
            'note' => $note,
            'sticky' => $sticky,
        ]);
    }
}
