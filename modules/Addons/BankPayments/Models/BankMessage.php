<?php

namespace Modules\Addons\BankPayments\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankMessage extends Model
{
    use HasFactory;

    protected $table = 'bank_messages';

    protected $fillable = [
        'bank_mailbox_id', 'uid', 'uidvalidity', 'message_id',
        'from_email', 'from_name', 'subject', 'received_at',
        'body_text', 'content_hash', 'status', 'error',
    ];

    protected function casts(): array
    {
        return [
            'uid' => 'integer',
            'uidvalidity' => 'integer',
            'received_at' => 'datetime',
        ];
    }

    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(BankMailbox::class, 'bank_mailbox_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }
}
