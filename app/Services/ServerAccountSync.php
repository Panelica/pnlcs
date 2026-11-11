<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Domain;
use App\Models\Product;
use App\Models\Server;
use App\Models\Service;
use App\Services\Module\ModuleRegistry;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Servers\Proxmox\ProxmoxModule;
use RuntimeException;
use Throwable;

/**
 * Brings the accounts that already run on a server into billing in one pass.
 *
 * The server's list is set against the services PNLCS already has: an account
 * a live service manages shows as linked, the rest are offered for import with
 * a guess at the client (by domain, then by the owner's email), the product
 * (the one sold with the account's plan) and the renewal (a price list row the
 * operator uploaded, else the product's price and the creation anniversary).
 * Importing writes the billing service and links it to the account the same
 * way a single "link existing account" does: nothing changes on the server.
 */
class ServerAccountSync
{
    public const CYCLES = ['Monthly', 'Quarterly', 'Semi-Annually', 'Annually', 'Biennially', 'Triennially'];

    /** The columns a price list may carry; client_email stands in for client_id. */
    public const PRICE_LIST_COLUMNS = ['domain', 'client_id', 'client_email', 'amount', 'billing_cycle', 'next_due_date'];

    private const CLOSED_STATUSES = ['terminated', 'cancelled', 'fraud'];

    public function __construct(private ModuleRegistry $registry) {}

    /**
     * The module for a server whose accounts can be listed and linked, or
     * null. A virtual server is claimed by tagging its guest, not imported here.
     */
    public function moduleFor(Server $server): ?object
    {
        $module = $this->registry->getServerModule((string) $server->type);

        if (! $module || ! method_exists($module, 'listAccounts') || $module instanceof ProxmoxModule) {
            return null;
        }

        return $module;
    }

    /**
     * The server's accounts, the same shape whatever the module: modules that
     * know more (plan, creation date) say so through listAccountDetails().
     *
     * @return list<array{id: string, domain: string, owner: string, email: string, status: string, plan: ?string, created: ?string, kind?: string, package?: ?string}>
     */
    public function accounts(Server $server, object $module): array
    {
        if (method_exists($module, 'listAccountDetails')) {
            return $module->listAccountDetails($server);
        }

        return array_map(fn (array $account): array => [
            'id' => (string) ($account['id'] ?? ''),
            'domain' => (string) ($account['domain'] ?? $account['username'] ?? ''),
            'owner' => (string) ($account['owner'] ?? ''),
            'email' => (string) ($account['email'] ?? ''),
            'status' => (string) ($account['status'] ?? 'active'),
            'plan' => $account['plan'] ?? null,
            'created' => $account['created'] ?? null,
            'kind' => $account['kind'] ?? 'account',
        ], $module->listAccounts($server));
    }

    /**
     * Each account with the live service that manages it, or the guesses an
     * import starts from.
     *
     * @param  array<string, array{client_id?: ?int, amount?: ?string, billing_cycle?: ?string, next_due_date?: ?string}>  $priceList  keyed by lower-case domain
     * @return list<array<string, mixed>>
     */
    public function rows(Server $server, object $module, array $priceList = []): array
    {
        $accounts = $this->accounts($server, $module);
        $linked = $this->linkedServices($server, $module);
        $domains = array_values(array_filter(array_map(fn ($a) => strtolower($a['domain']), $accounts)));

        $clientByDomain = Domain::whereIn('domain', $domains)->pluck('client_id', 'domain')
            ->union(Service::whereIn('domain', $domains)->whereNotNull('client_id')->pluck('client_id', 'domain'))
            ->mapWithKeys(fn ($clientId, $domain) => [strtolower((string) $domain) => (int) $clientId]);

        $emails = array_values(array_unique(array_filter(array_map(fn ($a) => strtolower($a['email']), $accounts))));
        $clientByEmail = $emails === [] ? collect() : Client::query()
            ->where(fn ($query) => $query->whereIn(DB::raw('LOWER(email)'), $emails)->orWhereIn(DB::raw('LOWER(billing_email)'), $emails))
            ->get(['id', 'email', 'billing_email'])
            ->flatMap(fn (Client $client) => array_fill_keys(array_filter([strtolower((string) $client->email), strtolower((string) $client->billing_email)]), $client->id));

        $products = $this->products($server);

        return array_map(function (array $account) use ($linked, $clientByDomain, $clientByEmail, $priceList, $products): array {
            $service = $linked->get($account['id']);
            $domain = strtolower($account['domain']);
            $listed = $priceList[$domain] ?? [];
            $product = $this->productFor($account['package'] ?? $account['plan'], $products);
            $cycle = in_array($listed['billing_cycle'] ?? null, self::CYCLES, true) ? $listed['billing_cycle'] : 'Annually';
            $price = $product?->priceFor($cycle);

            return $account + [
                'service' => $service,
                'client_id' => $listed['client_id'] ?? $clientByDomain->get($domain) ?? $clientByEmail->get(strtolower($account['email'])),
                'product_id' => $product?->id,
                'billing_cycle' => $cycle,
                'amount' => $listed['amount'] ?? ($price === null ? '' : number_format($price, 2, '.', '')),
                'next_due_date' => $listed['next_due_date'] ?? $this->nextAnniversary($account['created'], $cycle),
                'from_price_list' => $listed !== [],
            ];
        }, $accounts);
    }

    /**
     * The products a server of this type provisions, for the import form.
     *
     * @return Collection<int, Product>
     */
    public function products(Server $server): Collection
    {
        return Product::query()
            ->whereRaw('LOWER(server_type) = ?', [strtolower((string) $server->type)])
            ->where('retired', false)
            ->orderBy('name')
            ->get();
    }

    /**
     * Records one account as a billing service of a client and links it. A link
     * the module refuses leaves no service behind.
     *
     * @param  array{client_id: int, product_id: int, billing_cycle: string, amount: string|float, next_due_date: ?string}  $billing
     * @param  array{id: string, domain: string, status: string, created: ?string}  $account
     * @return array{success: bool, message: string, service: ?Service}
     */
    public function import(Server $server, object $module, array $account, array $billing, string $by): array
    {
        if ($this->linkedServices($server, $module)->has($account['id'])) {
            return ['success' => false, 'message' => __('admin.server_sync.already_linked', ['account' => $account['domain']]), 'service' => null];
        }

        try {
            return DB::transaction(function () use ($server, $module, $account, $billing, $by): array {
                $service = Service::create([
                    'client_id' => $billing['client_id'],
                    'product_id' => $billing['product_id'],
                    'server_id' => $server->id,
                    // A reseller account has a login, not a domain of its own.
                    'domain' => ($account['kind'] ?? null) === 'reseller' ? null : ($account['domain'] ?: null),
                    'qty' => 1,
                    'first_payment_amount' => $billing['amount'],
                    'amount' => $billing['amount'],
                    'billing_cycle' => $billing['billing_cycle'],
                    'next_due_date' => $billing['next_due_date'] ?: null,
                    'registration_date' => $account['created'] ?: now()->toDateString(),
                    'status' => $account['status'] === 'suspended' ? 'suspended' : 'active',
                    'auto_renew' => true,
                ]);

                if (is_callable([$module, 'linkAccount'])) {
                    $result = $module->linkAccount($service, $account['id']);
                    if (! ($result['success'] ?? false)) {
                        throw new RuntimeException((string) ($result['message'] ?? ''));
                    }
                } else {
                    $service->forceFill([
                        'module_data' => ['panelica_user_id' => $account['id']],
                        'username' => $service->username ?: ($account['domain'] ?: null),
                    ])->save();
                }

                ActivityLog::log("Existing account {$account['domain']} on server #{$server->id} imported as service #{$service->id}", $by, $service->client_id);

                return ['success' => true, 'message' => '', 'service' => $service];
            });
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $account['domain'].': '.$e->getMessage(), 'service' => null];
        }
    }

    /**
     * Reads an uploaded price list: a CSV with a header row naming the columns
     * domain, client_id or client_email, amount, billing_cycle, next_due_date.
     * Commas or semicolons; unknown columns and rows without a domain are skipped.
     *
     * @return array<string, array{client_id?: ?int, amount?: ?string, billing_cycle?: ?string, next_due_date?: ?string}>
     */
    public function readPriceList(string $path): array
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            return [];
        }

        $first = (string) fgets($handle);
        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        $header = array_map(fn ($h) => strtolower(trim((string) $h, " \t\n\r\0\x0B\u{FEFF}\"")), str_getcsv($first, $delimiter, '"', ''));

        $rows = [];
        $emails = [];
        while (($line = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
            $row = array_combine($header, array_pad(array_slice($line, 0, count($header)), count($header), ''));
            $domain = strtolower(trim((string) ($row['domain'] ?? '')));
            if ($domain === '' || isset($rows[$domain])) {
                continue;
            }

            $amount = str_replace(',', '.', trim((string) ($row['amount'] ?? '')));
            $due = trim((string) ($row['next_due_date'] ?? ''));
            $cycle = collect(self::CYCLES)->first(fn ($c) => strcasecmp($c, trim((string) ($row['billing_cycle'] ?? ''))) === 0);

            $rows[$domain] = array_filter([
                'client_id' => ctype_digit(trim((string) ($row['client_id'] ?? ''))) ? (int) $row['client_id'] : null,
                'amount' => is_numeric($amount) ? number_format((float) $amount, 2, '.', '') : null,
                'billing_cycle' => $cycle,
                'next_due_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $due) ? $due : null,
            ], fn ($value) => $value !== null);

            if (! isset($rows[$domain]['client_id']) && filled($row['client_email'] ?? null)) {
                $emails[$domain] = strtolower(trim((string) $row['client_email']));
            }
        }
        fclose($handle);

        if ($emails !== []) {
            $byEmail = Client::whereIn(DB::raw('LOWER(email)'), array_values($emails))->get(['id', 'email'])
                ->mapWithKeys(fn (Client $client) => [strtolower((string) $client->email) => $client->id]);
            foreach ($emails as $domain => $email) {
                if ($byEmail->has($email)) {
                    $rows[$domain]['client_id'] = $byEmail->get($email);
                }
            }
        }

        // Only clients that exist are suggested.
        $known = Client::whereIn('id', array_filter(array_column($rows, 'client_id')))->pluck('id')->all();
        foreach ($rows as $domain => $row) {
            if (isset($row['client_id']) && ! in_array($row['client_id'], $known, true)) {
                unset($rows[$domain]['client_id']);
            }
        }

        return $rows;
    }

    /**
     * Live services on the server keyed by the account they manage.
     *
     * @return Collection<string, Service>
     */
    private function linkedServices(Server $server, object $module): Collection
    {
        $accountOf = is_callable([$module, 'linkedPanelAccount'])
            ? fn (Service $service) => (string) $module->linkedPanelAccount($service)
            : fn (Service $service) => (string) (($service->module_data ?? [])['panelica_user_id'] ?? '');

        return Service::with('client')
            ->where('server_id', $server->id)
            ->whereNotIn('status', self::CLOSED_STATUSES)
            ->get()
            ->toBase()
            ->keyBy($accountOf)
            ->filter(fn (Service $service, int|string $account) => $account !== '');
    }

    /**
     * The product sold with a plan; with no plan to go by, the only product of
     * the server's type when there is just one.
     *
     * @param  Collection<int, Product>  $products
     */
    private function productFor(?string $plan, Collection $products): ?Product
    {
        if (filled($plan)) {
            $match = $products->first(function (Product $product) use ($plan): bool {
                $config = is_string($product->config_options)
                    ? (json_decode($product->config_options, true) ?: [])
                    : ($product->config_options ?? []);

                return in_array($plan, [$config['package_name'] ?? null, $config['plesk_plan'] ?? null], true);
            });
            if ($match) {
                return $match;
            }
        }

        return $products->count() === 1 ? $products->first() : null;
    }

    /**
     * The first renewal after today counted from the day the account was made.
     */
    private function nextAnniversary(?string $created, string $cycle): ?string
    {
        if (! $created) {
            return null;
        }

        $due = Carbon::parse($created)->startOfDay();
        $today = now()->startOfDay();
        for ($i = 0; $i < 400 && $due->lessThanOrEqualTo($today); $i++) {
            $due = BillingCycleHelper::advance($due, $cycle);
        }

        return $due->toDateString();
    }
}
