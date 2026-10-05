<?php

namespace App\Services\WhmcsImport;

use App\Models\Client;
use App\Models\CustomField;
use App\Models\CustomFieldValue;

/**
 * Runs the clients import. Read-only on the source, writes only to PNLCS, and
 * applies the three modes the operator picks: add new, add + update, or update
 * only, matched by a chosen key (email by default, but the operator decides).
 */
class ClientImporter
{
    public function __construct(
        protected MappingEngine $engine,
        protected ImportValidator $validator,
    ) {}

    /**
     * @param  callable(callable(array<string, mixed>): void): void  $rows  yields each source row to its callback
     * @param  array<string, mixed>  $mapping  ['columns' => ..., 'constants' => ...]
     * @return array{total: int, added: int, updated: int, skipped: int, errors: int, error_details: list<array<string, mixed>>}
     */
    public function run(
        callable $rows,
        array $mapping,
        string $importMode,
        ?string $matchKey,
    ): array {
        $summary = [
            'total' => 0,
            'added' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
            'error_details' => [],
        ];

        $rows(function (array $row) use ($mapping, $importMode, $matchKey, &$summary) {
            $summary['total']++;

            $target = $this->engine->apply($row, $mapping);

            $creating = ! $this->clientExists($matchKey, $target);
            $problem = $this->validator->record($target, $creating);
            if ($problem !== null) {
                $summary['errors']++;
                $summary['error_details'][] = $this->error($row, $problem);

                return;
            }

            try {
                [$client, $wasCreated] = $this->persist($target, $matchKey, $importMode);
            } catch (\Throwable $e) {
                $summary['errors']++;
                $summary['error_details'][] = $this->error($row, $e->getMessage());

                return;
            }

            if ($client === null) {
                $summary['skipped']++;

                return;
            }

            $wasCreated ? $summary['added']++ : $summary['updated']++;
        });

        return $summary;
    }

    /** Whether a client already exists under the chosen match key. */
    protected function clientExists(?string $matchKey, array $target): bool
    {
        if ($matchKey === null || $matchKey === '') {
            return false;
        }

        $value = $target[$matchKey] ?? null;

        return $value !== null && $value !== ''
            && Client::where($matchKey, $value)->exists();
    }

    /**
     * @return array{0: Client|null, 1: bool} the affected client (null = skipped) and whether it was created
     */
    protected function persist(array $target, ?string $matchKey, string $importMode): array
    {
        [$columns, $customValues] = $this->splitTarget($target);

        $existing = null;
        if ($matchKey !== null && $matchKey !== '') {
            $value = $columns[$matchKey] ?? null;
            if ($value !== null && $value !== '') {
                $existing = Client::where($matchKey, $value)->first();
            }
        }

        if ($existing !== null) {
            if ($importMode === 'add') {
                return [null, false];
            }

            $existing->update($columns);
            $this->storeCustomValues($existing, $customValues);

            return [$existing, false];
        }

        if ($importMode === 'update') {
            return [null, false];
        }

        $client = Client::create($columns);
        $this->storeCustomValues($client, $customValues);

        return [$client, true];
    }

    /**
     * Separate the model's mass-assignable columns from PNLCS client custom
     * fields, which live in their own table and are written separately.
     *
     * @param  array<string, mixed>  $target
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    protected function splitTarget(array $target): array
    {
        $columns = [];
        $custom = [];

        foreach ($target as $key => $value) {
            if (str_starts_with($key, 'custom_field:')) {
                $custom[substr($key, strlen('custom_field:'))] = $value;
            } else {
                $columns[$key] = $value;
            }
        }

        return [$columns, $custom];
    }

    /**
     * @param  array<string, mixed>  $customValues  field name => value
     */
    protected function storeCustomValues(Client $client, array $customValues): void
    {
        foreach ($customValues as $name => $value) {
            $field = CustomField::where('type', 'client')->where('field_name', $name)->first();
            if (! $field) {
                continue;
            }

            if ($value === null || trim((string) $value) === '') {
                CustomFieldValue::where('field_id', $field->id)->where('rel_id', $client->id)->delete();

                continue;
            }

            CustomFieldValue::updateOrCreate(
                ['field_id' => $field->id, 'rel_id' => $client->id],
                ['value' => (string) $value],
            );
        }
    }

    protected function error(array $row, string $message): array
    {
        return [
            'whmcs_id' => $row['id'] ?? null,
            'email' => $row['email'] ?? null,
            'error' => $message,
        ];
    }
}
