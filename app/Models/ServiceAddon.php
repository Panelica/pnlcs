<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceAddon extends Model
{
    use HasFactory;

    protected $fillable = ['order_id', 'service_id', 'addon_id', 'client_id', 'server_id', 'qty', 'amount', 'prorated', 'proration_days', 'proration_cycle_days', 'billing_cycle', 'next_due_date', 'first_invoice_date', 'status', 'notes'];

    protected function casts(): array
    {
        return [
            'next_due_date' => 'date',
            'first_invoice_date' => 'date',
            'amount' => 'decimal:2',
            'prorated' => 'boolean',
            'proration_days' => 'integer',
            'proration_cycle_days' => 'integer',
        ];
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
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

    public function isDraft(): bool
    {
        return strtolower((string) $this->status) === 'draft';
    }

    public function isPending(): bool
    {
        return strtolower((string) $this->status) === 'pending';
    }

    public function isSuspended(): bool
    {
        return strtolower((string) $this->status) === 'suspended';
    }

    /** Whether the first invoice covers part of the service's paid period. */
    public function isProrated(): bool
    {
        return (bool) $this->prorated;
    }

    /** The first invoice amount; full price unless proration is stored. */
    public function firstPeriodAmount(): float
    {
        $amount = (float) $this->amount;

        if (! $this->isProrated() || ! $this->proration_cycle_days) {
            return round($amount, 2);
        }

        return round($amount * ((int) $this->proration_days / (int) $this->proration_cycle_days), 2);
    }

    public function isEnded(): bool
    {
        return in_array(strtolower((string) $this->status), ['cancelled', 'terminated'], true);
    }

    /** The invoice lines that bill this addon. */
    public function invoiceLines()
    {
        return $this->hasMany(InvoiceItem::class, 'rel_id')->where('type', 'Addon');
    }

    /** Whether any invoice already carries this addon. */
    public function hasInvoiceLines(): bool
    {
        return $this->invoiceLines()->exists();
    }

    /**
     * Whether an invoice line sits on a document that must not change:
     * paid, cancelled or refunded. Those addons are cancelled, never deleted.
     * Lines on unpaid, overdue or draft invoices are still open and may be
     * removed together with the addon.
     */
    public function hasBlockingInvoiceLines(): bool
    {
        return $this->invoiceLines()
            ->whereHas('invoice', fn ($q) => $q->whereNotIn('status', ['unpaid', 'overdue', 'draft']))
            ->exists();
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
