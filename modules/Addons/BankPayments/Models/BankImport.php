<?php

namespace Modules\Addons\BankPayments\Models;

use App\Models\Admin;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankImport extends Model
{
    use HasFactory;

    protected $table = 'bank_imports';

    protected $fillable = [
        'bank_match_id', 'bank_transaction_id', 'invoice_id', 'admin_id',
        'source', 'gateway', 'payment_reference', 'amount', 'status',
        'response', 'error', 'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'response' => 'array',
            'imported_at' => 'datetime',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(BankMatch::class, 'bank_match_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class, 'bank_transaction_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
