<?php

namespace App\Models;

use App\Enums\ServiceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;

    protected $fillable = ['client_id', 'order_id', 'product_id', 'whmcs_product_name', 'server_id', 'domain', 'payment_method', 'qty', 'first_payment_amount', 'amount', 'billing_cycle', 'next_due_date', 'registration_date', 'status', 'username', 'password', 'disk_usage', 'disk_limit', 'bw_usage', 'bw_limit', 'suspension_date', 'suspension_reason', 'termination_date', 'notes', 'module_data', 'auto_renew', 'override_auto_suspend_date'];

    protected $hidden = ['password'];

    /**
     * How many months each billing cycle covers, so an amount charged once a
     * year is not read as an amount charged every month.
     *
     * Keyed on the cycle with spaces and hyphens stripped: the same cycle is
     * stored as "Semi-Annually", "semiannually" and "Annually" in different
     * places.
     */
    public const CYCLE_MONTHS = [
        'monthly' => 1,
        'quarterly' => 3,
        'semiannually' => 6,
        'annually' => 12,
        'biennially' => 24,
        'triennially' => 36,
    ];

    /**
     * The number of months this service's price covers. Anything unrecognised
     * counts as one month, which is what the code did before there was a map.
     */
    public static function monthsInCycle(?string $cycle): int
    {
        $key = strtolower(str_replace([' ', '-', '_'], '', (string) $cycle));

        return self::CYCLE_MONTHS[$key] ?? 1;
    }

    /**
     * The address a customer knows a server by, for lists: the one assigned
     * from the operator's pool, else the one the guest last reported (Proxmox),
     * or the instance's main IP (Vultr). Read from what the module recorded,
     * so a list never calls a server. Null when there is none yet.
     */
    public function primaryIp(): ?string
    {
        $data = is_array($this->module_data) ? $this->module_data : [];

        foreach (['pve_ipv4', 'pve_seen_ip', 'vultr_main_ip'] as $key) {
            $ip = (string) ($data[$key] ?? '');
            if (filter_var($ip, FILTER_VALIDATE_IP) && $ip !== '0.0.0.0') {
                return $ip;
            }
        }

        return null;
    }

    /**
     * Why the service is suspended, in words the customer may read: what staff
     * typed when they suspended it, or "Unpaid invoice" for a suspension
     * billing made (the text auto-suspend writes is for staff). Null when it is
     * not suspended or no reason was given.
     */
    public function customerSuspensionReason(): ?string
    {
        $reason = trim((string) $this->suspension_reason);

        if (strtolower((string) $this->status) !== 'suspended' || $reason === '') {
            return null;
        }

        $lower = strtolower($reason);

        return str_contains($lower, 'overdue') || str_contains($lower, 'unpaid')
            ? __('email.common.unpaid_invoice')
            : $reason;
    }

    /**
     * What this service is worth per month, whatever it is billed in.
     */
    public function monthlyAmount(): float
    {
        return round((float) $this->amount / self::monthsInCycle($this->billing_cycle), 2);
    }

    protected static function booted(): void
    {
        // An addon cannot outlive the service it hangs off. Several places end
        // a service - the provisioning module, the cancellation cron, an
        // operator on the admin screen - so this is enforced here rather than
        // in each of them.
        static::updated(function (self $service) {
            if (! $service->wasChanged('status')) {
                return;
            }

            if (! in_array(strtolower((string) $service->status), ['terminated', 'cancelled', 'fraud'], true)) {
                return;
            }

            ServiceAddon::where('service_id', $service->id)
                ->whereIn('status', ['active', 'pending'])
                ->update(['status' => 'cancelled', 'next_due_date' => null]);
        });
    }

    public function configOptions()
    {
        return $this->hasMany(ServiceConfigOption::class);
    }

    protected function casts(): array
    {
        return ['next_due_date' => 'date', 'registration_date' => 'date', 'suspension_date' => 'date', 'termination_date' => 'date', 'amount' => 'decimal:2', 'first_payment_amount' => 'decimal:2', 'auto_renew' => 'boolean', 'password' => 'encrypted', 'module_data' => 'array'];
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function server()
    {
        return $this->belongsTo(Server::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function addons()
    {
        return $this->hasMany(ServiceAddon::class);
    }

    public function sslOrder()
    {
        return $this->hasOne(SslOrder::class);
    }

    public function cancellationRequest()
    {
        return $this->hasOne(CancellationRequest::class);
    }

    public function scopeActive($q)
    {
        return $q->where('status', ServiceStatus::Active->value);
    }

    /**
     * Services on an invoice that has been overdue longer than the grace
     * period: as the service itself, its usage overage, or one of its addons.
     * What AutoSuspendInvoicedOnly suspends over (SuspensionCommand).
     */
    public function scopeOwingOverdue($q, int $graceDays)
    {
        return $q->whereExists(fn ($e) => $e->selectRaw('1')->from('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoices.status', \App\Enums\InvoiceStatus::Overdue->value)
            ->where('invoices.due_date', '<', now()->subDays($graceDays))
            ->where(fn ($w) => $w
                ->where(fn ($own) => $own->whereIn('invoice_items.type', ['Hosting', 'Service', 'hosting', 'service', 'Overage'])
                    ->whereColumn('invoice_items.rel_id', 'services.id'))
                ->orWhere(fn ($addon) => $addon->where('invoice_items.type', 'Addon')
                    ->whereIn('invoice_items.rel_id', fn ($a) => $a->select('id')->from('service_addons')->whereColumn('service_addons.service_id', 'services.id')))));
    }

    /** A service the customer can still act on (not terminated/cancelled/fraud). */
    public function isLive(): bool
    {
        return ! in_array(strtolower((string) $this->status), ['terminated', 'cancelled', 'fraud'], true);
    }

    /**
     * Hosting self-service tools this service offers, asked of its own module.
     *
     * Lives here rather than in the service page's controller because the
     * dashboard and the service list link to these tools too, and all three
     * have to agree on which ones exist. Reads only what is already loaded -
     * module data and product config - so listing pages pay no API calls.
     *
     * @return string[]
     */
    public function hostingFeatureKeys(): array
    {
        if (! $this->server_id || ! $this->isLive()) {
            return [];
        }

        $module = app(\App\Services\ProvisioningService::class)->resolveModule($this);

        return $module && method_exists($module, 'hostingFeatures')
            ? $module->hostingFeatures($this)
            : [];
    }
}
