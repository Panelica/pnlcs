<?php

namespace App\Services\WhmcsImport;

use App\Models\Client;

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
        $existing = null;
        if ($matchKey !== null && $matchKey !== '') {
            $value = $target[$matchKey] ?? null;
            if ($value !== null && $value !== '') {
                $existing = Client::where($matchKey, $value)->first();
            }
        }

        if ($existing !== null) {
            if ($importMode === 'add') {
                return [null, false];
            }

            $existing->update($target);

            return [$existing, false];
        }

        if ($importMode === 'update') {
            return [null, false];
        }

        $client = Client::create($target);

        return [$client, true];
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
