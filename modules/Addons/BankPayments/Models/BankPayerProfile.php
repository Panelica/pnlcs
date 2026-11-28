<?php

namespace Modules\Addons\BankPayments\Models;

use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Addons\BankPayments\Support\PayerName;

class BankPayerProfile extends Model
{
    protected $table = 'bank_payer_profiles';

    protected $fillable = [
        'bank', 'sender_name', 'sender_name_normalized', 'sender_account',
        'client_id', 'uses', 'last_matched_at',
    ];

    protected function casts(): array
    {
        return [
            'uses' => 'integer',
            'last_matched_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public static function forTransaction(BankTransaction $transaction): ?self
    {
        $normalized = PayerName::normalize($transaction->sender_name);

        if ($normalized === '') {
            return null;
        }

        return static::query()
            ->where('bank', $transaction->bank)
            ->where('sender_name_normalized', $normalized)
            ->first();
    }

    public static function remember(BankTransaction $transaction, Invoice $invoice): ?self
    {
        $normalized = PayerName::normalize($transaction->sender_name);

        if ($normalized === '' || ! $invoice->client_id) {
            return null;
        }

        $profile = static::firstOrNew([
            'bank' => $transaction->bank,
            'sender_name_normalized' => $normalized,
        ]);

        $profile->sender_name = (string) $transaction->sender_name;
        $profile->sender_account = $transaction->sender_account;
        $profile->client_id = $invoice->client_id;
        $profile->uses = ($profile->exists ? $profile->uses : 0) + 1;
        $profile->last_matched_at = now();
        $profile->save();

        return $profile;
    }
}
