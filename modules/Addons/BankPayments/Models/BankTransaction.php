<?php

namespace Modules\Addons\BankPayments\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BankTransaction extends Model
{
    use HasFactory;

    protected $table = 'bank_transactions';

    protected $fillable = [
        'bank_message_id', 'bank', 'operation_type', 'transaction_date',
        'amount', 'currency', 'sender_name', 'sender_account',
        'recipient_account', 'reference', 'raw_payload', 'fingerprint',
        'matching_status', 'matching_score', 'matching_reason', 'matched_at',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'amount' => 'decimal:2',
            'raw_payload' => 'array',
            'matching_score' => 'integer',
            'matched_at' => 'datetime',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(BankMessage::class, 'bank_message_id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(BankMatch::class)->orderBy('rank');
    }

    public function import(): HasOne
    {
        return $this->hasOne(BankImport::class, 'bank_transaction_id');
    }
}
