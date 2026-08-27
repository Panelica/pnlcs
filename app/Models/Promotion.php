<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Promotion extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'type', 'recurring', 'value', 'cycles', 'applies_to', 'requires', 'start_date', 'expiration_date', 'max_uses', 'uses', 'lifetime_promo', 'apply_once', 'new_signups_only', 'existing_client', 'upgrades', 'notes'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'expiration_date' => 'date', 'recurring' => 'boolean', 'lifetime_promo' => 'boolean', 'apply_once' => 'boolean', 'new_signups_only' => 'boolean', 'existing_client' => 'boolean', 'upgrades' => 'boolean', 'value' => 'decimal:2'];
    }

    /**
     * The rules on top of validity: which products the code covers, one use
     * per customer, new customers only, existing customers only.
     *
     * @param  array<int, int>  $productIds  what is in the basket
     */
    public function isValidFor(?Client $client, array $productIds = []): bool
    {
        if (! $this->isValid()) {
            return false;
        }

        $covered = $this->coveredProductIds();

        if ($covered !== [] && array_intersect($covered, array_map('intval', $productIds)) === []) {
            return false;
        }

        if (! $client) {
            // A guest in the cart. This check is provisional - the order
            // re-validates against the real client at placement - so only the
            // rule a guest can never satisfy refuses here. Refusing
            // new_signups_only turned the code away from exactly the people
            // it exists for, before they had any way to sign up.
            return ! $this->existing_client;
        }

        $hasOrdered = Order::where('client_id', $client->id)->exists();

        if ($this->new_signups_only && $hasOrdered) {
            return false;
        }

        if ($this->existing_client && ! $hasOrdered) {
            return false;
        }

        if ($this->apply_once && $this->usedBy($client)) {
            return false;
        }

        return true;
    }

    /** @return array<int, int> */
    public function coveredProductIds(): array
    {
        $raw = trim((string) $this->applies_to);

        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        $ids = is_array($decoded) ? $decoded : explode(',', $raw);

        return array_values(array_filter(array_map('intval', $ids)));
    }

    private function usedBy(Client $client): bool
    {
        return Order::where('client_id', $client->id)
            ->whereRaw('LOWER(promo_code) = ?', [strtolower((string) $this->code)])
            ->exists();
    }

    public function isValid(): bool
    {
        if ($this->max_uses > 0 && $this->uses >= $this->max_uses) {
            return false;
        }
        // Inclusive, like every other end date here: a quote is actionable
        // through its valid_until day and a suspension hold that ends today
        // still holds. The date cast put this at midnight, refusing the code
        // for the whole of its own last day.
        if ($this->expiration_date && $this->expiration_date->endOfDay()->isPast()) {
            return false;
        }
        if ($this->start_date && $this->start_date->isFuture()) {
            return false;
        }

        return true;
    }
}
