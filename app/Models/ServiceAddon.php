<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceAddon extends Model
{
    use HasFactory;

    protected $fillable = ['order_id', 'service_id', 'addon_id', 'client_id', 'server_id', 'qty', 'amount', 'billing_cycle', 'next_due_date', 'status', 'notes'];

    protected function casts(): array
    {
        return ['next_due_date' => 'date', 'amount' => 'decimal:2'];
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function addon()
    {
        return $this->belongsTo(ProductAddon::class, 'addon_id');
    }

    /** What to call this on an invoice or in the customer's service page. */
    public function label(): string
    {
        return $this->addon?->name ?: __('client.services.addon_prefix', ['id' => $this->addon_id ?? $this->id]);
    }

    public function isActive(): bool
    {
        return strtolower((string) $this->status) === 'active';
    }

    /**
     * Addons that should be billed: paid for and not cancelled. The column
     * collation is case-insensitive, so this matches Active as well.
     */
    public function scopeBillable($query)
    {
        return $query->where('status', 'active');
    }
}
