<?php

namespace App\Models;

use App\Enums\ClientStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Client extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        "first_name",
        "last_name",
        "company_name",
        "email",
        "address1",
        "address2",
        "city",
        "state",
        "postcode",
        "country",
        "phone_number",
        "tax_id",
        "status",
        "group_id",
        "currency_id",
        "credit",
        "tax_exempt",
        "language",
        "notes",
        "ip_address",
    ];

    protected function casts(): array
    {
        return [
            "status" => ClientStatus::class,
            "credit" => "decimal:2",
            "tax_exempt" => "boolean",
            "late_fee_overide" => "boolean",
            "override_auto_suspend" => "boolean",
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Client $client) {
            if (empty($client->uuid)) {
                $client->uuid = (string) Str::uuid();
            }
        });
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->company_name ?: $this->full_name;
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, "user_client")
            ->withPivot("owner", "permissions")
            ->withTimestamps();
    }

    public function contacts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function owner(): ?User
    {
        return $this->users()->wherePivot("owner", true)->first();
    }

    public function scopeActive($query)
    {
        return $query->where("status", ClientStatus::Active);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where("first_name", "ilike", "%{$term}%")
                ->orWhere("last_name", "ilike", "%{$term}%")
                ->orWhere("email", "ilike", "%{$term}%")
                ->orWhere("company_name", "ilike", "%{$term}%");
        });
    }
}
