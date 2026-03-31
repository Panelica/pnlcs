<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdminRole extends Model
{
    use HasFactory;

    protected $fillable = [
        "name", "description", "is_full_admin", "widgets",
        "permissions", "system_emails", "account_emails", "support_emails",
    ];

    protected function casts(): array
    {
        return [
            "is_full_admin" => "boolean",
            "widgets" => "array",
            "permissions" => "array",
            "system_emails" => "boolean",
            "account_emails" => "boolean",
            "support_emails" => "boolean",
        ];
    }

    public function admins(): HasMany
    {
        return $this->hasMany(Admin::class, "role_id");
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->is_full_admin) {
            return true;
        }

        return in_array($permission, $this->permissions ?? []);
    }
}
