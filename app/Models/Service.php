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
