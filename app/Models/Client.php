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

        // Cascade delete: clean up all child records
        static::deleting(function (Client $client) {
            $client->services()->delete();
            $client->invoices()->each(function($inv) { $inv->items()->delete(); $inv->delete(); });
            $client->tickets()->each(function($t) { $t->replies()->delete(); $t->notes()->delete(); $t->delete(); });
            $client->domains()->delete();
            $client->contacts()->delete();
            $client->orders()->delete();
            \App\Models\Affiliate::where("client_id", $client->id)->delete();
            \App\Models\Credit::where("client_id", $client->id)->delete();
            \App\Models\ClientNote::where("client_id", $client->id)->delete();
            $client->users()->detach(); // Remove pivot entries
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

    public function invoices(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function services(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function domains(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Domain::class);
    }

    public function tickets(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Ticket::class);
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
            $q->where("first_name", "like", "%{$term}%")
                ->orWhere("last_name", "like", "%{$term}%")
                ->orWhere("email", "like", "%{$term}%")
                ->orWhere("company_name", "like", "%{$term}%");
        });

        // Cascade delete: clean up all child records
        static::deleting(function (Client $client) {
            $client->services()->delete();
            $client->invoices()->each(function($inv) { $inv->items()->delete(); $inv->delete(); });
            $client->tickets()->each(function($t) { $t->replies()->delete(); $t->notes()->delete(); $t->delete(); });
            $client->domains()->delete();
            $client->contacts()->delete();
            $client->orders()->delete();
            \App\Models\Affiliate::where("client_id", $client->id)->delete();
            \App\Models\Credit::where("client_id", $client->id)->delete();
            \App\Models\ClientNote::where("client_id", $client->id)->delete();
            $client->users()->detach(); // Remove pivot entries
        });
    }
}
