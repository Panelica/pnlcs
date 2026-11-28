<?php

namespace Modules\Addons\BankPayments\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankMailbox extends Model
{
    use HasFactory;

    protected $table = 'bank_mailboxes';

    protected $fillable = [
        'name', 'host', 'port', 'encryption', 'username', 'password',
        'folder', 'bank', 'parser', 'active', 'validate_cert',
        'last_uid', 'last_uidvalidity', 'last_sync_at', 'last_error',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'password' => 'encrypted',
            'active' => 'boolean',
            'validate_cert' => 'boolean',
            'last_uid' => 'integer',
            'last_uidvalidity' => 'integer',
            'last_sync_at' => 'datetime',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(BankMessage::class);
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(BankSyncLog::class);
    }
}
