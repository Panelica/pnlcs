<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;

class Admin extends Authenticatable
{
    use HasFactory;

    protected $fillable = [
        "role_id", "username", "email", "password", "first_name", "last_name",
        "signature", "is_disabled", "support_departments", "ticket_notifications",
        "second_factor_type", "second_factor_secret", "backup_codes", "language",
        "last_login", "last_login_ip",
    ];

    protected $hidden = [
        "password", "remember_token", "second_factor_secret", "backup_codes",
    ];

    protected function casts(): array
    {
        return [
            "password" => "hashed",
            "is_disabled" => "boolean",
            "support_departments" => "array",
            "ticket_notifications" => "boolean",
            "last_login" => "datetime",
            // As on the customer side: stored encrypted, one entry per code.
            "backup_codes" => "encrypted:array",
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Admin $admin) {
            if (empty($admin->uuid)) {
                $admin->uuid = (string) Str::uuid();
            }
        });

        // Any change to the password or the second factor invalidates every
        // remembered browser. A replaced password must not leave an old
        // "trusted" cookie able to skip the new second factor.
        static::updated(function (Admin $admin) {
            if ($admin->wasChanged(['password', 'second_factor_type', 'second_factor_secret'])) {
                $admin->trustedDevices()->delete();
            }
        });
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(AdminRole::class, "role_id");
    }

    public function trustedDevices(): HasMany
    {
        return $this->hasMany(AdminTrustedDevice::class);
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function hasPermission(string $permission): bool
    {
        return $this->role->hasPermission($permission);
    }

    /** Whether this member of staff handles the ticket department, as ticked on the staff form. */
    public function handlesDepartment(?int $departmentId): bool
    {
        return $departmentId !== null
            && in_array($departmentId, array_map('intval', (array) ($this->support_departments ?? [])), true);
    }
}
