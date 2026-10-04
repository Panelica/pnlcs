<?php

namespace App\Services;

use App\Enums\ClientStatus;
use App\Enums\ServiceStatus;
use App\Models\Client;
use Illuminate\Database\Eloquent\Builder;

/**
 * The clients a message is for, picked by what they have rather than by name:
 * everyone on a product, everyone with a service on one server, a client
 * group, an account status, everyone holding a domain of one extension.
 * Filters combine (all must hold); an empty filter is ignored.
 */
class ClientAudience
{
    /**
     * @param  array{product_ids?: array, server_ids?: array, service_status?: ?string, group_id?: mixed, client_status?: ?string, tld?: ?string}  $filters
     * @return list<int>
     */
    public function matching(array $filters): array
    {
        return $this->query($filters)->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public static function hasFilters(array $filters): bool
    {
        return collect(['product_ids', 'server_ids', 'service_status', 'group_id', 'client_status', 'tld'])
            ->contains(fn ($key) => filled($filters[$key] ?? null));
    }

    private function query(array $filters): Builder
    {
        $query = Client::query();

        $products = array_filter(array_map('intval', (array) ($filters['product_ids'] ?? [])));
        $servers = array_filter(array_map('intval', (array) ($filters['server_ids'] ?? [])));
        $serviceStatus = in_array($filters['service_status'] ?? null, array_column(ServiceStatus::cases(), 'value'), true) ? $filters['service_status'] : null;

        // One service has to satisfy the service filters together: "active on
        // server 2" is not "has an active service, and a service on server 2".
        if ($products || $servers || $serviceStatus) {
            $query->whereHas('services', function ($q) use ($products, $servers, $serviceStatus) {
                if ($products) {
                    $q->whereIn('product_id', $products);
                }
                if ($servers) {
                    $q->whereIn('server_id', $servers);
                }
                if ($serviceStatus) {
                    $q->where('status', $serviceStatus);
                }
            });
        }

        if (filled($filters['group_id'] ?? null)) {
            $query->where('group_id', (int) $filters['group_id']);
        }

        if (in_array($filters['client_status'] ?? null, array_column(ClientStatus::cases(), 'value'), true)) {
            $query->where('status', $filters['client_status']);
        }

        $tld = strtolower(ltrim(trim((string) ($filters['tld'] ?? '')), '.'));
        if ($tld !== '' && preg_match('/^[a-z0-9.-]{2,63}$/', $tld)) {
            $query->whereHas('domains', fn ($q) => $q->where('domain', 'like', '%.'.$tld)->whereNotIn('status', ['cancelled', 'transferred-away']));
        }

        return $query;
    }
}
