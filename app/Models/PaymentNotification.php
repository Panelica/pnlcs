<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentNotification extends Model
{
    protected $fillable = [
        'invoice_id', 'client_id', 'gateway', 'sender_name', 'bank_name',
        'amount', 'transfer_date', 'reference', 'receipt_path', 'client_note',
        'status', 'admin_id', 'admin_note', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount'        => 'decimal:2',
            'transfer_date' => 'date',
            'reviewed_at'   => 'datetime',
        ];
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
