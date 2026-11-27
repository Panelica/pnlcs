<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KsefImportedInvoice extends Model
{
    protected $fillable = [
        'ksef_number', 'direction', 'invoice_number', 'issue_date',
        'invoicing_date', 'permanent_storage_date', 'seller_nip', 'seller_name',
        'buyer_nip', 'buyer_name', 'net', 'vat', 'gross', 'currency',
        'metadata', 'xml', 'local_invoice_id', 'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'invoicing_date' => 'date',
            'permanent_storage_date' => 'date',
            'net' => 'decimal:2',
            'vat' => 'decimal:2',
            'gross' => 'decimal:2',
            'metadata' => 'array',
            'imported_at' => 'datetime',
        ];
    }

    public function localInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'local_invoice_id');
    }
}
