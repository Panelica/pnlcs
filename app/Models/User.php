<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        "first_name",
        "last_name",
        "email",
        "password",
        "second_factor_type",
        "second_factor_secret",
        "backup_codes",
        "language",
        "last_login",
        "last_login_ip",
        "is_active",
    ];

    protected $hidden = [
        "password",
        "remember_token",
        "second_factor_secret",
        "backup_codes",
    ];

    protected function casts(): array
    {
        return [
            "email_verified_at" => "datetime",
            "password" => "hashed",
            "last_login" => "datetime",
            "is_active" => "boolean",
            "backup_codes" => "encrypted:array",
        ];
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function clients(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Client::class, "user_client")
            ->withPivot("owner", "permissions")
            ->withTimestamps();
    }
}
