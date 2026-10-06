<?php

namespace App\Services\WhmcsImport;

use App\Models\Client;
use App\Models\CustomField;
use App\Models\Domain;
use Illuminate\Support\Facades\Schema;

/**
 * Reads the two schemas the mapper lines up: the source WHMCS table (columns
 * discovered from the live database, so extra columns in a particular
 * installation show up too) and the PNLCS target (the Client model's fillable
 * fields, so the list never hardcodes a column and follows the model).
 */
class SchemaReader
{
    /**
     * @return list<array{name: string, type: string}>
     */
    public function whmcsColumns(WhmcsConnector $connector, string $table): array
    {
        return $connector->columns($table);
    }

    /**
     * WHMCS client custom fields (PESEL/NIP, CSA, …). These are not columns
     * of tblclients; they live in tblcustomfields and are read per-client.
     *
     * @return list<array{id: int, name: string}>
     */
    public function whmcsCustomFields(WhmcsConnector $connector, string $prefix): array
    {
        try {
            return $connector->clientCustomFields($prefix);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * The PNLCS client fields an import may write to: the model's fillable
     * columns plus any client custom fields (namespaced `custom_field:{name}`
     * so they cannot collide with a real column of the same name).
     *
     * @return list<string>
     */
    public function clientTargetFields(): array
    {
        $fields = (new Client)->getFillable();

        // Internal counters/flags are mass-assignable for the app's own forms
        // but importing them from WHMCS makes no sense; keep them out of the
        // mapper so they cannot be filled with source garbage by accident.
        $fields = array_values(array_diff($fields, [
            'credit',
            'auto_charge',
            'affiliate_id',
            'notes',
            'ip_address',
        ]));

        foreach (CustomField::clientFields()->get(['field_name']) as $field) {
            $fields[] = 'custom_field:'.$field->field_name;
        }

        return $fields;
    }

    /**
     * The columns actually present on the clients table, for validation that a
     * target field really exists.
     *
     * @return list<string>
     */
    public function clientColumns(): array
    {
        return Schema::getColumnListing((new Client)->getTable());
    }

    /**
     * The PNLCS domain fields an import may write to. `client_id` is filled by
     * the importer itself (matched by email), so it is not offered as a target.
     *
     * @return list<string>
     */
    public function domainTargetFields(): array
    {
        $fields = (new Domain)->getFillable();

        return array_values(array_diff($fields, [
            'client_id',
            'order_id',
            'epp_code',
            'last_sync_at',
            'last_sync_status',
            'renewal_reminder_stage',
            'renewal_reminder_sent_at',
        ]));
    }
}
