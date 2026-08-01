<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Domain extends Model
{
    use HasFactory;

    protected $fillable = ['client_id', 'order_id', 'type', 'domain', 'registrar', 'registration_period', 'registration_date', 'expiry_date', 'next_due_date', 'status', 'dns_management', 'email_forwarding', 'id_protection', 'is_premium', 'payment_method', 'first_payment_amount', 'recurring_amount', 'nameservers', 'notes'];

    protected function casts(): array
    {
        return ['registration_date' => 'date', 'expiry_date' => 'date', 'next_due_date' => 'date', 'dns_management' => 'boolean', 'email_forwarding' => 'boolean', 'id_protection' => 'boolean', 'is_premium' => 'boolean', 'first_payment_amount' => 'decimal:2', 'recurring_amount' => 'decimal:2'];
    }

    /**
     * Whether this domain is set to renew.
     *
     * There is no column for it. The customer's switch flips payment_method to
     * "none", and that is what the invoice generator reads - a null counts as
     * renewing, the same way it does there. The screens used to read a
     * non-existent attribute and therefore always said no.
     */
    public function getAutoRenewAttribute(): bool
    {
        return ($this->payment_method ?? '') !== 'none';
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
