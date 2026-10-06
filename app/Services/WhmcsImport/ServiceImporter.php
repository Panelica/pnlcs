<?php

namespace App\Services\WhmcsImport;

use App\Enums\ServiceStatus;
use App\Models\Client;
use App\Models\Product;
use App\Models\Server;
use App\Models\Service;

/**
 * Imports WHMCS hosting services. The mapping works like the other importers,
 * but three references are resolved instead of mapped: the owning client (by
 * email), the product (by name, through packageid) and the server (by name,
 * through server). A missing product or server leaves the reference null and is
 * noted in the log so the operator can fill the gap; a missing client is an
 * error, since a service cannot exist without one.
 */
class ServiceImporter
{
    public function __construct(
        protected MappingEngine $engine,
        protected ImportValidator $validator,
    ) {}

    /**
     * @param  callable(callable(array<string, mixed>): void): void  $rows  yields each service row (enriched with `client_email`, `product_name`, `server_name`) to its callback
     * @param  array<string, mixed>  $mapping  ['columns' => ..., 'constants' => ...]
     * @return array{total: int, added: int, updated: int, skipped: int, errors: int, error_details: list<array<string, mixed>>, skipped_details: list<array<string, mixed>>}
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
            'skipped_details' => [],
        ];

        $statuses = array_map(fn (ServiceStatus $s) => $s->value, ServiceStatus::cases());

        $rows(function (array $row) use ($mapping, $importMode, $matchKey, $statuses, &$summary) {
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

            $productName = trim((string) ($row['product_name'] ?? ''));
            if ($productName !== '') {
                $product = Product::where('name', $productName)->first();
                if ($product !== null) {
                    $target['product_id'] = $product->id;
                } else {
                    $summary['skipped_details'][] = $this->error($row, __('whmcs_import.validation.product_not_found', ['name' => $productName]));
                }
            }

            $serverId = (int) ($row['server'] ?? 0);
            if ($serverId > 0) {
                $hostname = trim((string) ($row['server_hostname'] ?? ''));
                $serverName = trim((string) ($row['server_name'] ?? ''));

                $server = null;
                if ($hostname !== '') {
                    $server = Server::where('hostname', $hostname)->first();
                }
                if ($server === null && $serverName !== '') {
                    $server = Server::where('name', $serverName)->first();
                }

                if ($server !== null) {
                    $target['server_id'] = $server->id;
                } else {
                    $summary['skipped_details'][] = $this->error($row, __('whmcs_import.validation.server_not_found', ['name' => $serverName ?: $hostname ?: (string) $serverId]));
                }
            }

            $creating = ! $this->serviceExists($matchKey, $target);
            $problem = $this->validator->record($target, $creating, [], $statuses);
            if ($problem !== null) {
                $summary['errors']++;
                $summary['error_details'][] = $this->error($row, $problem);

                return;
            }

            try {
                [$service, $wasCreated, $skipReason] = $this->persist($target, $matchKey, $importMode);
            } catch (\Throwable $e) {
                $summary['errors']++;
                $summary['error_details'][] = $this->error($row, $e->getMessage());

                return;
            }

            if ($service === null) {
                $summary['skipped']++;
                $summary['skipped_details'][] = $this->error($row, $skipReason ?? '');

                return;
            }

            $wasCreated ? $summary['added']++ : $summary['updated']++;
        });

        return $summary;
    }

    protected function serviceExists(?string $matchKey, array $target): bool
    {
        if ($matchKey === null || $matchKey === '') {
            return false;
        }

        $value = $target[$matchKey] ?? null;

        return $value !== null && $value !== ''
            && Service::where($matchKey, $value)->exists();
    }

    /**
     * @return array{0: Service|null, 1: bool, 2: string|null}
     */
    protected function persist(array $target, ?string $matchKey, string $importMode): array
    {
        $existing = null;
        if ($matchKey !== null && $matchKey !== '') {
            $value = $target[$matchKey] ?? null;
            if ($value !== null && $value !== '') {
                $existing = Service::where($matchKey, $value)->first();
            }
        }

        if ($existing !== null) {
            if ($importMode === 'add') {
                return [null, false, __('whmcs_import.log.skip_exists')];
            }

            $existing->update($target);

            return [$existing, false, null];
        }

        if ($importMode === 'update') {
            return [null, false, __('whmcs_import.log.skip_not_found')];
        }

        $service = Service::create($target);

        return [$service, true, null];
    }

    /**
     * WHMCS stores empty dates as MySQL's zero date; blank/zero dates become null.
     *
     * @param  array<string, mixed>  $target
     * @return array<string, mixed>
     */
    protected function normalizeDates(array $target): array
    {
        foreach (['registration_date', 'next_due_date', 'suspension_date', 'termination_date'] as $field) {
            $value = (string) ($target[$field] ?? '');
            if ($value === '' || str_starts_with($value, '0000-00-00') || str_starts_with($value, '-0001-')) {
                $target[$field] = null;
            }
        }

        return $target;
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
