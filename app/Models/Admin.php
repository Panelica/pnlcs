<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;

class Admin extends Authenticatable
{
    use HasFactory;

    protected $fillable = [
        "role_id", "username", "email", "password", "first_name", "last_name",
        "signature", "is_disabled", "support_departments", "ticket_notifications",
        "second_factor_type", "second_factor_secret", "language",
        "last_login", "last_login_ip",
    ];

    protected $hidden = [
        "password", "remember_token", "second_factor_secret",
    ];

    protected function casts(): array
    {
        return [
            "password" => "hashed",
            "is_disabled" => "boolean",
            "support_departments" => "array",
            "ticket_notifications" => "boolean",
            "last_login" => "datetime",
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Admin $admin) {
            if (empty($admin->uuid)) {
                $admin->uuid = (string) Str::uuid();
            }
        });
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(AdminRole::class, "role_id");
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function hasPermission(string $permission): bool
    {
        return $this->role->hasPermission($permission);
    }
}
