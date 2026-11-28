<?php

namespace Modules\Addons\BankPayments\Models;

use App\Models\Admin;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Addons\BankPayments\Services\InvoiceMatcher;

class BankMatch extends Model
{
    use HasFactory;

    protected $table = 'bank_matches';

    protected $fillable = [
        'bank_transaction_id', 'invoice_id', 'rank', 'score', 'amount',
        'criteria', 'status', 'reason', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'rank' => 'integer',
            'score' => 'integer',
            'amount' => 'decimal:2',
            'criteria' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function scorePercent(): int
    {
        return (int) round($this->score / InvoiceMatcher::MAX_SCORE * 100);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class, 'bank_transaction_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }

    public function import(): HasOne
    {
        return $this->hasOne(BankImport::class);
    }
}
