<?php

namespace App\Models;

use App\Services\BillingCycleHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory, \Illuminate\Database\Eloquent\SoftDeletes;

    protected $fillable = ['type', 'group_id', 'name', 'slug', 'description', 'hidden', 'show_domain_options', 'is_featured', 'retired', 'pay_type', 'auto_setup', 'server_type', 'server_group_id', 'stock_control', 'stock_qty', 'welcome_email_template', 'sort_order', 'config_options', 'tax', 'ssl_module', 'overage_enabled', 'overage_disk_rate', 'overage_bw_rate'];

    protected function casts(): array
    {
        return ['hidden' => 'boolean', 'is_featured' => 'boolean', 'retired' => 'boolean', 'stock_control' => 'boolean', 'tax' => 'boolean', 'overage_enabled' => 'boolean', 'overage_disk_rate' => 'decimal:4', 'overage_bw_rate' => 'decimal:4', 'config_options' => 'array'];
    }

    public function group()
    {
        return $this->belongsTo(ProductGroup::class, 'group_id');
    }

    /** Whether the shelf is empty. A product without stock control never is. */
    public function outOfStock(): bool
    {
        return (bool) $this->stock_control && (int) $this->stock_qty <= 0;
    }

    public function serverGroup()
    {
        return $this->belongsTo(ServerGroup::class, 'server_group_id');
    }

    public function pricing()
    {
        return $this->hasMany(Pricing::class, 'rel_id')->where('type', 'product');
    }

    /**
     * The price row for a currency — the one being sold in unless asked
     * otherwise, and whatever exists if the operator never priced that one.
     */
    public function pricingFor(?int $currencyId = null): ?Pricing
    {
        $currencyId ??= Currency::getDefault()?->id;

        $rows = $this->relationLoaded('pricing') ? $this->pricing : $this->pricing()->get();

        return $rows->firstWhere('currency_id', $currencyId) ?? $rows->first();
    }

    /**
     * What one term costs, or null when the product is not sold on that cycle.
     *
     * The pricing table marks an unsold cycle with -1, which is a marker and
     * not a price: anything that treats it as one bills a negative amount.
     */
    public function priceFor(string $cycle, ?int $currencyId = null): ?float
    {
        $column = BillingCycleHelper::pricingColumn($cycle);
        $row = $column ? $this->pricingFor($currencyId) : null;

        if (! $row || $row->{$column} === null) {
            return null;
        }

        $price = round((float) $row->{$column}, 2);

        return $price > 0 ? $price : null;
    }

    /**
     * The cycles the product is actually sold on, cheapest term first.
     *
     * @return array<string, float>
     */
    public function pricedCycles(?int $currencyId = null): array
    {
        $prices = [];

        foreach (['monthly', 'quarterly', 'semiannually', 'annually', 'biennially', 'triennially'] as $cycle) {
            $price = $this->priceFor($cycle, $currencyId);

            if ($price !== null) {
                $prices[$cycle] = $price;
            }
        }

        return $prices;
    }

    public function services()
    {
        return $this->hasMany(Service::class, 'product_id');
    }

    /** Configurable option groups linked to this product. */
    public function configOptionGroups()
    {
        return $this->belongsToMany(ConfigOptionGroup::class, 'config_option_links', 'product_id', 'group_id');
    }

    public function scopeActive($q)
    {
        return $q->where('hidden', false)->where('retired', false);
    }

    /**
     * What the plan actually gives, read from the product's own configuration.
     *
     * Written out rather than typed into a feature list, because a hand-written
     * "1 GB RAM" goes stale the moment somebody edits the limits and nobody
     * remembers to edit the marketing copy too. These are the numbers the panel
     * will enforce.
     *
     * @return array<int, array{icon: string, text: string}>
     */
    public function resourceSummary(): array
    {
        $c = is_string($this->config_options) ? (json_decode($this->config_options, true) ?: []) : ((array) $this->config_options);
        if ($c === []) {
            return [];
        }

        $mb = function ($v) {
            $v = (int) $v;

            return $v >= 1024 ? rtrim(rtrim(number_format($v / 1024, 1, '.', ''), '0'), '.').' GB' : $v.' MB';
        };

        $out = [];
        if (($c['res_memory_mb'] ?? 0) > 0) {
            $out[] = ['icon' => 'ri-ram-2-line', 'text' => __('client.store.res_memory', ['value' => $mb($c['res_memory_mb'])])];
        }
        if (($c['res_cpu_percent'] ?? 0) > 0) {
            // 100% is one core, so say it in cores - a percentage over 100 reads
            // like an error to anyone who has not seen a cgroup.
            $cores = (int) $c['res_cpu_percent'] / 100;
            $out[] = ['icon' => 'ri-cpu-line', 'text' => __('client.store.res_cpu', ['value' => rtrim(rtrim(number_format($cores, 1, '.', ''), '0'), '.')])];
        }
        if (($c['res_disk_mb'] ?? 0) > 0) {
            $out[] = ['icon' => 'ri-hard-drive-2-line', 'text' => __('client.store.res_disk', ['value' => $mb($c['res_disk_mb'])])];
        }
        if (($c['res_bandwidth_mb'] ?? 0) > 0) {
            $out[] = ['icon' => 'ri-exchange-line', 'text' => __('client.store.res_bandwidth', ['value' => $mb($c['res_bandwidth_mb'])])];
        }
        if (($c['res_max_containers'] ?? 0) > 0) {
            $out[] = ['icon' => 'ri-apps-2-line', 'text' => trans_choice('client.store.res_apps', (int) $c['res_max_containers'], ['count' => (int) $c['res_max_containers']])];
        }
        if (($c['res_max_domains'] ?? 0) > 0) {
            $out[] = ['icon' => 'ri-global-line', 'text' => trans_choice('client.store.res_domains', (int) $c['res_max_domains'], ['count' => (int) $c['res_max_domains']])];
        }

        return $out;
    }
}
