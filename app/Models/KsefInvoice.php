<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KsefInvoice extends Model
{
    protected $fillable = [
        'invoice_id',
        'session_reference',
        'status',
        'ksef_number',
        'corrected_by_invoice_id',
        'sent_at',
        'accepted_at',
        'attempts',
        'error_message',
        'request_xml',
        'response_xml',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function correction(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'corrected_by_invoice_id');
    }
}
