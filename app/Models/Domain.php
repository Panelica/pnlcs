<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Domain extends Model
{
    use HasFactory;

    protected $fillable = ['client_id', 'order_id', 'type', 'domain', 'registrar', 'registration_period', 'registration_date', 'expiry_date', 'next_due_date', 'status', 'dns_management', 'email_forwarding', 'id_protection', 'is_premium', 'payment_method', 'first_payment_amount', 'recurring_amount', 'nameservers', 'notes'];

    protected function casts(): array
    {
        return ['registration_date' => 'date', 'expiry_date' => 'date', 'next_due_date' => 'date', 'dns_management' => 'boolean', 'email_forwarding' => 'boolean', 'id_protection' => 'boolean', 'is_premium' => 'boolean', 'first_payment_amount' => 'decimal:2', 'recurring_amount' => 'decimal:2'];
    }

    /**
     * Whether this domain is set to renew.
     *
     * There is no column for it. The customer's switch flips payment_method to
     * "none", and that is what the invoice generator reads - a null counts as
     * renewing, the same way it does there. The screens used to read a
     * non-existent attribute and therefore always said no.
     */
    public function getAutoRenewAttribute(): bool
    {
        return ($this->payment_method ?? '') !== 'none';
    }

    /**
     * The domain a customer meant, from whatever they typed.
     *
     * The search box lower-cased its input and the order form did not, so the
     * same address arrived in two shapes depending on which box it went in.
     * People also paste URLs rather than domains, and a scheme, a www or a
     * trailing dot would be handed to the panel and the registrar as part of
     * the name.
     */
    public static function normalise(?string $domain): string
    {
        $domain = strtolower(trim((string) $domain));

        if ($domain === '') {
            return '';
        }

        // Scheme, then anything after the host: a pasted address brings both.
        $domain = preg_replace('#^[a-z][a-z0-9+.-]*://#', '', $domain) ?? $domain;
        $domain = preg_split('#[/?\#]#', $domain)[0] ?? $domain;

        // Credentials and a port, if the paste carried them.
        if (str_contains($domain, '@')) {
            $domain = substr($domain, strrpos($domain, '@') + 1);
        }
        $domain = preg_replace('/:\d+$/', '', $domain) ?? $domain;

        // "www." is a host under the domain, never the domain being ordered.
        // Only as a label of its own: wwwshop.com keeps its name.
        if (str_starts_with($domain, 'www.')) {
            $domain = substr($domain, 4);
        }

        return trim($domain, '.');
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
