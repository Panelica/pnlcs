<?php

namespace App\Services\WhmcsImport;

use App\Enums\ServiceStatus;
use App\Models\Client;
use App\Models\ClientNote;
use App\Models\Product;
use App\Models\Server;
use App\Models\Service;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

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
     * @param  array<string, int>  $productMap  WHMCS product id (from `packageid`) => PNLCS product id
     * @return array{total: int, added: int, updated: int, skipped: int, errors: int, error_details: list<array<string, mixed>>, skipped_details: list<array<string, mixed>>}
     */
    public function run(
        callable $rows,
        array $mapping,
        string $importMode,
        ?string $matchKey,
        array $productMap = [],
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

        // Resolve products once per run: an explicit mapping wins, otherwise
        // the name. The valid-id set also catches a mapping whose product has
        // since been deleted.
        $productsByName = Product::pluck('id', 'name');
        $validProductIds = Product::pluck('id')->flip();

        $linesByClient = [];

        $rows(function (array $row) use ($mapping, $importMode, $matchKey, $productMap, $productsByName, $validProductIds, $statuses, &$summary, &$linesByClient) {
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

            // Keep the source line so the operator can verify the import
            // against the WHMCS database.
            $linesByClient[$client->id][] = $this->sourceLine($row);

            $productName = trim((string) ($row['product_name'] ?? ''));
            if ($productName !== '') {
                // The source product name is always part of the service
                // identity, so two products on one domain stay apart.
                $target['whmcs_product_name'] = $productName;

                // An explicit WHMCS → PNLCS product mapping (keyed by the WHMCS
                // product id) wins; otherwise the importer falls back to
                // matching the product by name.
                $mappedId = $productMap[(string) ($row['packageid'] ?? '')] ?? null;
                if ($mappedId !== null) {
                    if ($validProductIds->has((int) $mappedId)) {
                        $target['product_id'] = (int) $mappedId;
                    } else {
                        $summary['skipped_details'][] = $this->error($row, __('whmcs_import.validation.product_id_not_found', ['id' => $mappedId]));
                    }
                } else {
                    $productId = $productsByName[$productName] ?? null;
                    if ($productId !== null) {
                        $target['product_id'] = $productId;
                    } else {
                        $summary['skipped_details'][] = $this->error($row, __('whmcs_import.validation.product_not_found', ['name' => $productName]));
                    }
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

        $this->writeSourceNotes($linesByClient);

        return $summary;
    }

    protected function serviceExists(?string $matchKey, array $target): bool
    {
        return $this->findExisting($matchKey, $target) !== null;
    }

    /**
     * A service is identified by its match key, client and WHMCS product name
     * together. The product name keeps two products on one domain — e.g.
     * hosting and e-mail — apart, whether or not they are mapped to a PNLCS
     * product.
     */
    protected function findExisting(?string $matchKey, array $target): ?Service
    {
        $query = $this->scope($matchKey, $target);
        if ($query === null) {
            return null;
        }

        return $query->where('client_id', $target['client_id'])->first()
            ?? $this->importedWithoutProductName($matchKey, $target);
    }

    /**
     * A service imported before the WHMCS product name was recorded (1.4.0)
     * has no name to match on, and a re-import would add a copy of it. It is
     * taken as this row's service when it is the only such service of the
     * client under the key and runs the same product - so hosting is never
     * matched to an e-mail row on the same domain.
     */
    protected function importedWithoutProductName(string $matchKey, array $target): ?Service
    {
        $name = $target['whmcs_product_name'] ?? null;
        if ($name === null || $name === '') {
            return null;
        }

        $query = Service::where($matchKey, $target[$matchKey])
            ->where('client_id', $target['client_id'])
            ->whereNull('whmcs_product_name');

        isset($target['product_id'])
            ? $query->where('product_id', $target['product_id'])
            : $query->whereNull('product_id');

        $candidates = $query->limit(2)->get();

        return $candidates->count() === 1 ? $candidates->first() : null;
    }

    /**
     * The base query for a service's identity, without the client constraint:
     * the match key plus the WHMCS product name. A row with no source product
     * name matches services imported before this rule (where the column is
     * null).
     *
     * @return Builder|null null when the match key cannot be resolved
     */
    protected function scope(?string $matchKey, array $target): ?Builder
    {
        if ($matchKey === null || $matchKey === '') {
            return null;
        }

        $value = $target[$matchKey] ?? null;
        if ($value === null || $value === '') {
            return null;
        }

        $query = Service::where($matchKey, $value);

        $name = $target['whmcs_product_name'] ?? null;
        if ($name === null || $name === '') {
            $query->whereNull('whmcs_product_name');
        } else {
            $query->where('whmcs_product_name', $name);
        }

        return $query;
    }

    /** Whether the match key already belongs to a different client. */
    protected function keyTakenByOtherClient(?string $matchKey, array $target): bool
    {
        if ($matchKey === null || $matchKey === '') {
            return false;
        }

        $value = $target[$matchKey] ?? null;
        if ($value === null || $value === '') {
            return false;
        }

        return Service::where($matchKey, $value)
            ->where('client_id', '!=', $target['client_id'])
            ->exists();
    }

    /**
     * @return array{0: Service|null, 1: bool, 2: string|null}
     */
    protected function persist(array $target, ?string $matchKey, string $importMode): array
    {
        $existing = $this->findExisting($matchKey, $target);

        // Only the client's own record is ever matched. A record with the same
        // key that belongs to someone else is never taken over.
        if ($existing === null && $this->keyTakenByOtherClient($matchKey, $target)) {
            return [null, false, __('whmcs_import.log.skip_other_client')];
        }

        if ($existing !== null) {
            if ($importMode === 'add') {
                return [null, false, __('whmcs_import.log.skip_exists')];
            }

            // An update never changes who owns the service, nor the product
            // and server a live account is provisioned on. The WHMCS product
            // name is part of the identity and stays as it is - except on a
            // service imported before it was recorded, which gets it now so the
            // next run matches it directly.
            $except = ['client_id', 'product_id', 'server_id'];
            if ($existing->whmcs_product_name !== null) {
                $except[] = 'whmcs_product_name';
            }
            $existing->update(Arr::except($target, $except));

            return [$existing, false, null];
        }

        if ($importMode === 'update') {
            return [null, false, __('whmcs_import.log.skip_not_found')];
        }

        $service = Service::create($target);

        return [$service, true, null];
    }

    /**
     * A human-readable line describing a WHMCS source service, used in the
     * client note that helps the operator verify the import.
     */
    protected function sourceLine(array $row): string
    {
        return sprintf(
            'id=%s domain=%s product=%s status=%s cycle=%s amount=%s',
            $row['id'] ?? '',
            $row['domain'] ?? '',
            $row['product_name'] ?? '',
            $row['domainstatus'] ?? '',
            $row['billingcycle'] ?? '',
            $row['amount'] ?? '',
        );
    }

    /**
     * Write one note per client listing their WHMCS services, so the operator
     * can verify the import against the source database. Re-running the same
     * import does not duplicate an identical note.
     *
     * @param  array<int, list<string>>  $linesByClient
     */
    protected function writeSourceNotes(array $linesByClient): void
    {
        foreach ($linesByClient as $clientId => $lines) {
            $note = __('whmcs_import.note_services_header')."\n".implode("\n", $lines);

            if (ClientNote::where('client_id', $clientId)->where('note', $note)->exists()) {
                continue;
            }

            ClientNote::create([
                'client_id' => $clientId,
                'admin' => 'WHMCS Import',
                'note' => $note,
                'sticky' => false,
            ]);
        }
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
