<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Domain;
use App\Models\InvoiceItem;

/**
 * Move a domain to another client account. Nothing changes at the registry;
 * the WHOIS contact is the new owner's to update. Used by staff (admin domain
 * page) and when a customer accepts a domain another customer offered them.
 */
class DomainMove
{
    /** Why the domain cannot move now, or null when it can. */
    public function problem(Domain $domain, Client $to): ?string
    {
        if ((int) $to->id === (int) $domain->client_id) {
            return 'same_client';
        }

        // An unpaid invoice carrying the domain would stay with the old
        // account and renew a domain that is no longer theirs.
        $billed = InvoiceItem::where('type', 'Domain')->where('rel_id', $domain->id)
            ->whereHas('invoice', fn ($q) => $q->outstanding())->exists();

        return $billed ? 'open_invoice' : null;
    }

    public function move(Domain $domain, Client $to, ?string $by): void
    {
        $from = $domain->client_id;
        $domain->update(['client_id' => $to->id]);

        ActivityLog::log("Domain {$domain->domain} moved to client #{$to->id}", $by, $from);
        ActivityLog::log("Domain {$domain->domain} moved here from client #{$from}", $by, $to->id);
    }
}
