<?php

namespace App\Services\WhmcsImport;

use App\Enums\DomainStatus;
use App\Models\Client;
use App\Models\Domain;

/**
 * Imports WHMCS domains. The mapping works exactly like the clients importer,
 * with one difference: the owning client is not a mapped field. It is found by
 * following the WHMCS domain's `userid` to the WHMCS client email and matching
 * that email against the PNLCS clients.
 */
class DomainImporter
{
    public function __construct(
        protected MappingEngine $engine,
        protected ImportValidator $validator,
    ) {}

    /**
     * @param  callable(callable(array<string, mixed>): void): void  $rows  yields each domain row (already enriched with `client_email`) to its callback
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

        $required = ['domain'];
        $statuses = array_map(fn (DomainStatus $s) => $s->value, DomainStatus::cases());

        $rows(function (array $row) use ($mapping, $importMode, $matchKey, $required, $statuses, &$summary) {
            $summary['total']++;

            $target = $this->normalizeDates($this->engine->apply($row, $mapping));

            $email = trim((string) ($row['client_email'] ?? ''));
            $client = $email !== '' ? Client::where('email', $email)->first() : null;

            if ($client === null) {
                $summary['errors']++;
                $summary['error_details'][] = $this->error($row, __('whmcs_import.validation.client_not_found', ['email' => $email ?: '-']));

                return;
            }

            $target['client_id'] = $client->id;

            $creating = ! $this->domainExists($matchKey, $target);
            $problem = $this->validator->record($target, $creating, $required, $statuses);
            if ($problem !== null) {
                $summary['errors']++;
                $summary['error_details'][] = $this->error($row, $problem);

                return;
            }

            try {
                [$domain, $wasCreated] = $this->persist($target, $matchKey, $importMode);
            } catch (\Throwable $e) {
                $summary['errors']++;
                $summary['error_details'][] = $this->error($row, $e->getMessage());

                return;
            }

            if ($domain === null) {
                $summary['skipped']++;

                return;
            }

            $wasCreated ? $summary['added']++ : $summary['updated']++;
        });

        return $summary;
    }

    protected function domainExists(?string $matchKey, array $target): bool
    {
        if ($matchKey === null || $matchKey === '') {
            return false;
        }

        $value = $target[$matchKey] ?? null;

        return $value !== null && $value !== ''
            && Domain::where($matchKey, $value)->exists();
    }

    /**
     * WHMCS writes an empty date as MySQL's zero date "0000-00-00", which
     * Eloquent's date cast turns into an out-of-range datetime that the column
     * rejects. Blank and zero dates become null so they are stored as "unknown".
     *
     * @param  array<string, mixed>  $target
     * @return array<string, mixed>
     */
    protected function normalizeDates(array $target): array
    {
        foreach (['registration_date', 'expiry_date', 'next_due_date'] as $field) {
            $value = (string) ($target[$field] ?? '');
            if ($value === '' || str_starts_with($value, '0000-00-00') || str_starts_with($value, '-0001-')) {
                $target[$field] = null;
            }
        }

        return $target;
    }

    /**
     * @return array{0: Domain|null, 1: bool} the affected domain (null = skipped) and whether it was created
     */
    protected function persist(array $target, ?string $matchKey, string $importMode): array
    {
        $existing = null;
        if ($matchKey !== null && $matchKey !== '') {
            $value = $target[$matchKey] ?? null;
            if ($value !== null && $value !== '') {
                $existing = Domain::where($matchKey, $value)->first();
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

        $domain = Domain::create($target);

        return [$domain, true];
    }

    protected function error(array $row, string $message): array
    {
        return [
            'whmcs_id' => $row['id'] ?? null,
            'domain' => $row['domain'] ?? null,
            'email' => $row['client_email'] ?? null,
            'error' => $message,
        ];
    }
}
