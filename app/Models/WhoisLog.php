<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One domain search on the storefront: the name asked about and what the
 * search answered (true free, false taken, null unanswered). Pruned by
 * pnlcs:prune-logs (retention_whois_logs_days, 90 by default).
 */
class WhoisLog extends Model
{
    protected $table = 'whois_logs';

    protected $fillable = ['domain', 'available', 'client_id', 'source', 'date'];

    protected function casts(): array
    {
        return ['available' => 'boolean', 'date' => 'datetime'];
    }

    /** Record a search when the operator keeps them (DomainSearchLog, on by default). Never breaks the search. */
    public static function record(string $domain, ?bool $available, ?int $clientId, string $source): void
    {
        try {
            if ((string) Setting::get('DomainSearchLog', '1') !== '1') {
                return;
            }
            static::create(['domain' => strtolower($domain), 'available' => $available, 'client_id' => $clientId, 'source' => $source, 'date' => now()]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Domain search not recorded: '.$e->getMessage());
        }
    }
}
