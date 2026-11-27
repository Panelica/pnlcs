<?php

namespace Modules\Addons\BankPayments\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankSyncLog extends Model
{
    use HasFactory;

    protected $table = 'bank_sync_logs';

    protected $fillable = [
        'bank_mailbox_id', 'started_at', 'finished_at', 'status',
        'fetched', 'parsed', 'new_transactions', 'duplicates', 'errors', 'message',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'fetched' => 'integer',
            'parsed' => 'integer',
            'new_transactions' => 'integer',
            'duplicates' => 'integer',
            'errors' => 'integer',
        ];
    }

    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(BankMailbox::class, 'bank_mailbox_id');
    }
}
