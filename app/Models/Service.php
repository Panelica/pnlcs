<?php

namespace App\Models;

use App\Enums\ServiceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;

    protected $fillable = ['client_id', 'order_id', 'product_id', 'server_id', 'domain', 'payment_method', 'qty', 'first_payment_amount', 'amount', 'billing_cycle', 'next_due_date', 'registration_date', 'status', 'username', 'password', 'disk_usage', 'disk_limit', 'bw_usage', 'bw_limit', 'suspension_date', 'suspension_reason', 'termination_date', 'notes', 'module_data', 'auto_renew', 'override_auto_suspend_date'];

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
}
