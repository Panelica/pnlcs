<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Client;

/**
 * Which customer account the client area is looking at.
 *
 * A login can belong to more than one account. Every page used to ask for the
 * first one, so the second account's invoices, services and domains were
 * unreachable — and an admin using "log in as this customer" landed on
 * whichever account came first rather than the one they clicked.
 */
trait ResolvesClient
{
    protected function currentClient(): ?Client
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        $selected = session('active_client_id');

        if ($selected) {
            $client = $user->clients()->whereKey($selected)->first();

            if ($client) {
                return $client;
            }
        }

        return $user->clients()->first();
    }

    /** Zero when there is no account, so a query for it matches nothing. */
    protected function getClientId(): int
    {
        return $this->currentClient()?->id ?? 0;
    }
}
