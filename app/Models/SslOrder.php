<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SslOrder extends Model
{
    use HasFactory;

    protected $table = 'ssl_orders';

    protected $fillable = [
        'client_id', 'service_id', 'remote_id', 'module', 'cert_type',
        'config_data', 'status', 'domain', 'domains', 'webserver_type',
        'validation_method', 'csr', 'cert', 'ca_cert', 'fullchain', 'private_key',
        'admin_first_name', 'admin_last_name', 'admin_email', 'admin_phone',
        'admin_org', 'admin_address', 'admin_city', 'admin_state',
        'admin_zip', 'admin_country', 'completion_date', 'crt_expires',
        'approver_email', 'order_date', 'last_polled_at',
        'expiry_notice_days', 'expiry_notice_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'config_data' => 'array',
            'private_key' => 'encrypted',
            'completion_date' => 'datetime',
            'crt_expires' => 'date',
            'order_date' => 'datetime',
            'last_polled_at' => 'datetime',
            'expiry_notice_sent_at' => 'datetime',
        ];
    }

    // Relations
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    // Scopes
    public function scopeAwaitingConfiguration($query)
    {
        return $query->where('status', 'Awaiting Configuration');
    }

    public function scopeAwaitingIssuance($query)
    {
        return $query->where('status', 'Awaiting Issuance');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'Completed');
    }

    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->where('status', 'Completed')
            ->whereNotNull('crt_expires')
            ->where('crt_expires', '<=', now()->addDays($days))
            ->where('crt_expires', '>', now());
    }

    public function scopePendingPoll($query)
    {
        return $query->whereIn('status', ['Awaiting Issuance', 'Configuration Submitted'])
            ->where(function ($q) {
                $q->whereNull('last_polled_at')
                    ->orWhere('last_polled_at', '<', now()->subMinutes(5));
            });
    }

    // Helpers
    public function isConfigured(): bool
    {
        return ! empty($this->csr) && ! empty($this->validation_method);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'Completed' && ! empty($this->cert);
    }

    public function daysUntilExpiry(): ?int
    {
        if (! $this->crt_expires) {
            return null;
        }

        return (int) now()->diffInDays($this->crt_expires, false);
    }

    public function getSanDomainsArray(): array
    {
        if (empty($this->domains)) {
            return [];
        }

        return array_filter(array_map('trim', explode("\n", $this->domains)));
    }

    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            'Completed' => 'bg-success',
            'Awaiting Configuration' => 'bg-warning text-dark',
            'Awaiting Issuance', 'Configuration Submitted' => 'bg-info',
            'Cancelled', 'Revoked', 'Expired' => 'bg-danger',
            default => 'bg-secondary',
        };
    }
}
