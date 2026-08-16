<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = ['date', 'client_id', 'invoice_id', 'description', 'user', 'ip_address'];

    protected function casts(): array
    {
        return ['date' => 'datetime'];
    }

    public static function log(string $description, ?string $user = null, ?int $clientId = null, ?int $invoiceId = null): void
    {
        static::create([
            'date' => now(),
            'client_id' => $clientId,
            'invoice_id' => $invoiceId,
            'description' => $description,
            'user' => $user,
            'ip_address' => request()->ip(),
        ]);
    }

    /**
     * Everything recorded about one invoice.
     */
    public function scopeForInvoice($query, $invoice)
    {
        return $query->where('invoice_id', $invoice->id)->orderBy('date', 'desc');
    }

    /**
     * Everything recorded about one customer.
     *
     * This used to be asked as a description search for "client #1", which
     * also matched client #12 and every other id starting with a 1.
     */
    public function scopeForClient($query, Client $client)
    {
        return $query->where(function ($q) use ($client) {
            $q->where('client_id', $client->id);

            if ($client->email) {
                $q->orWhere('description', 'like', '%'.$client->email.'%');
            }
        });
    }
}
