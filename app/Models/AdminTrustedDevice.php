<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A browser that has already passed an administrator's second factor.
 *
 * The cookie holds a random token; only its SHA-256 hash is stored. A device
 * is scoped to one admin, so a cookie from another account cannot pass.
 */
class AdminTrustedDevice extends Model
{
    public const COOKIE_NAME = 'pnlcs_admin_2fa_device';

    public const DEFAULT_DAYS = 30;

    public const MAX_DAYS = 365;

    protected $fillable = [
        'admin_id', 'token_hash', 'expires_at', 'last_used_at', 'ip_address', 'user_agent',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public static function issue(Admin $admin, int $days, ?string $ip = null, ?string $userAgent = null): string
    {
        $days = max(1, min(self::MAX_DAYS, $days));
        $token = Str::random(64);

        static::where('admin_id', $admin->id)
            ->where('expires_at', '<=', now())
            ->delete();

        static::create([
            'admin_id' => $admin->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays($days),
            'last_used_at' => now(),
            'ip_address' => $ip,
            'user_agent' => $userAgent ? Str::limit($userAgent, 255, '') : null,
        ]);

        return $token;
    }

    public static function findValid(Admin $admin, ?string $token): ?self
    {
        if (! $token) {
            return null;
        }

        return static::query()
            ->where('admin_id', $admin->id)
            ->where('token_hash', hash('sha256', $token))
            ->where('expires_at', '>', now())
            ->first();
    }

    public function markUsed(): void
    {
        $this->forceFill(['last_used_at' => now()])->saveQuietly();
    }
}
