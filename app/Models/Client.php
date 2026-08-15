<?php

namespace App\Models;

use App\Enums\ClientStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Client extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'company_name',
        'email',
        'address1',
        'address2',
        'city',
        'state',
        'postcode',
        'country',
        'phone_number',
        'phone_prefix',
        'tax_id',
        'status',
        'group_id',
        'currency_id',
        'credit',
        'tax_exempt',
        'language',
        'notes',
        'affiliate_id',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'status' => ClientStatus::class,
            'credit' => 'decimal:2',
            'tax_exempt' => 'boolean',
            'late_fee_overide' => 'boolean',
            'override_auto_suspend' => 'boolean',
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

            // Invoices and credits stay. Together with the transactions they
            // are the record of what was charged and what was paid, and a
            // client being removed from the list is not a reason for last
            // year's books to change. The client row itself is only soft
            // deleted, so they still point at something.
            $client->tickets()->each(function ($t) {
                $t->replies()->delete();
                $t->notes()->delete();
                $t->delete();
            });
            $client->domains()->delete();
            $client->contacts()->delete();
            $client->orders()->delete();
            Affiliate::where('client_id', $client->id)->delete();
            ClientNote::where('client_id', $client->id)->delete();
            $client->users()->detach(); // Remove pivot entries
        });
    }

    /**
     * Services that have not been terminated.
     *
     * Deleting the client takes these rows with it, and nothing closes the
     * accounts on the control panel, so the hosting would carry on running
     * with nothing left to say it exists.
     */
    public function liveServiceCount(): int
    {
        return $this->services()
            ->whereNotIn('status', ['terminated', 'cancelled', 'fraud'])
            ->count();
    }

    /**
     * Domains still registered in this customer's name.
     *
     * The same reason services are counted: deleting the customer takes the
     * domain row with it, and then nothing renews the registration and nothing
     * says it exists, while it carries on at the registrar until it lapses.
     */
    public function liveDomainCount(): int
    {
        return $this->domains()
            ->whereNotIn('status', ['cancelled', 'expired', 'transferred_away', 'fraud'])
            ->count();
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->company_name ?: $this->full_name;
    }

    /** Phone with its international prefix, e.g. "+48 123 456 789". */
    public function getFullPhoneAttribute(): ?string
    {
        if (empty($this->phone_number)) {
            return null;
        }

        $prefix = $this->phone_prefix ?: \App\Support\Countries::phonePrefix($this->country);

        return $prefix ? trim($prefix.' '.$this->phone_number) : $this->phone_number;
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_client')
            ->withPivot('owner', 'permissions')
            ->withTimestamps();
    }

    /** The affiliate that referred this client (set at registration). */
    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class, 'affiliate_id');
    }

    /** Client group (carries the suspend/terminate exemption flags). */
    public function group(): BelongsTo
    {
        return $this->belongsTo(ClientGroup::class, 'group_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function owner(): ?User
    {
        return $this->users()->wherePivot('owner', true)->first();
    }

    public function scopeActive($query)
    {
        return $query->where('status', ClientStatus::Active);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('company_name', 'like', "%{$term}%");
        });
    }
}
