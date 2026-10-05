<?php

namespace App\Services\WhmcsImport;

use App\Models\Client;
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
     * The PNLCS client fields an import may write to.
     *
     * @return list<string>
     */
    public function clientTargetFields(): array
    {
        $fields = (new Client)->getFillable();

        // Internal counters/flags are mass-assignable for the app's own forms
        // but importing them from WHMCS makes no sense; keep them out of the
        // mapper so they cannot be filled with source garbage by accident.
        return array_values(array_diff($fields, [
            'credit',
            'auto_charge',
            'affiliate_id',
            'notes',
            'ip_address',
        ]));
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
}
