<?php

namespace App\Services;

use App\Models\Domain;

/**
 * What a registrar sync changes on a domain, shared by the nightly
 * domains:sync command and the admin Sync button so the two cannot disagree.
 *
 * The registry is authoritative for the expiry date and status. The next due
 * date belongs to billing: it moves with the expiry only while it was still
 * following it (the way an order writes the two), and a due date set apart
 * from the expiry stays where it is.
 */
class DomainSyncChanges
{
    /**
     * Only report genuine differences so an unchanged domain is never written.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    public static function between(Domain $domain, array $result): array
    {
        $changes = [];

        // The registrar's own creation date fills a blank one first; RDAP is
        // only the fallback, run by the callers afterwards, and never overwrites.
        $registration = $result['registration_date'] ?? null;
        if ($registration && ! $domain->registration_date) {
            $changes['registration_date'] = $registration;
        }

        $expiry = $result['expiry_date'] ?? null;
        if ($expiry && optional($domain->expiry_date)->toDateString() !== $expiry) {
            $changes['expiry_date'] = $expiry;

            // The due date was following the expiry date, so it keeps
            // following it. One set apart from it stays where it is.
            if ($domain->expiry_date && $domain->next_due_date
                && $domain->next_due_date->toDateString() === $domain->expiry_date->toDateString()) {
                $changes['next_due_date'] = $expiry;
            }
        }

        $status = $result['status'] ?? null;
        if ($status && strtolower((string) $domain->status) !== strtolower($status)) {
            $changes['status'] = $status;
        }

        $privacy = $result['id_protection'] ?? null;
        if (is_bool($privacy) && (bool) $domain->id_protection !== $privacy) {
            $changes['id_protection'] = $privacy;
        }

        $nameservers = $result['nameservers'] ?? [];
        if ($nameservers) {
            $encoded = json_encode(array_values($nameservers));
            if ($domain->nameservers !== $encoded) {
                $changes['nameservers'] = $encoded;
            }
        }

        $contactId = $result['registrar_contact_id'] ?? null;
        if ($contactId && (string) $domain->registrar_contact_id !== (string) $contactId) {
            $changes['registrar_contact_id'] = (string) $contactId;
        }

        return $changes;
    }
}
