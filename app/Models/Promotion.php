<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Promotion extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'type', 'recurring', 'value', 'cycles', 'billing_cycles', 'max_products', 'applies_to', 'requires', 'start_date', 'expiration_date', 'max_uses', 'uses', 'lifetime_promo', 'apply_once', 'new_signups_only', 'existing_client', 'upgrades', 'notes'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'expiration_date' => 'date', 'recurring' => 'boolean', 'lifetime_promo' => 'boolean', 'apply_once' => 'boolean', 'new_signups_only' => 'boolean', 'existing_client' => 'boolean', 'upgrades' => 'boolean', 'value' => 'decimal:2'];
    }

    /**
     * The rules on top of validity: which products the code covers, one use
     * per customer, new customers only, existing customers only.
     *
     * @param  array<int, int>  $productIds  what is in the basket
     * @param  int|null  $placingOrderId  the order being placed with this code, if it already exists
     */
    public function isValidFor(?Client $client, array $productIds = [], ?int $placingOrderId = null): bool
    {
        if (! $this->isValid()) {
            return false;
        }

        $covered = $this->coveredProductIds();

        if ($covered !== [] && array_intersect($covered, array_map('intval', $productIds)) === []) {
            return false;
        }

        // A set: the code only holds when every required product is in the
        // basket. Stored and never checked, so a "hosting + domain + SSL"
        // discount went to anyone buying just one of them.
        if (array_diff($this->requiredProductIds(), array_map('intval', $productIds)) !== []) {
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

        // The order being placed is created before its invoice, and the code is
        // checked on that invoice. Counting it made every first order look like
        // a returning customer's, and every once-per-customer code look used.
        $hasOrdered = Order::where('client_id', $client->id)
            ->when($placingOrderId, fn ($q) => $q->whereKeyNot($placingOrderId))
            ->exists();

        if ($this->new_signups_only && $hasOrdered) {
            return false;
        }

        if ($this->existing_client && ! $hasOrdered) {
            return false;
        }

        if ($this->apply_once && $this->usedBy($client, $placingOrderId)) {
            return false;
        }

        return true;
    }

    /**
     * The billing periods the code applies to, as Service::CYCLE_MONTHS keys
     * ('monthly', 'annually', ...). Empty: every period.
     *
     * @return array<int, string>
     */
    public function billingCycles(): array
    {
        $raw = trim((string) $this->billing_cycles);

        if ($raw === '') {
            return [];
        }

        return array_values(array_intersect(
            array_map(fn ($c) => self::cycleKey($c), explode(',', $raw)),
            array_keys(Service::CYCLE_MONTHS),
        ));
    }

    /**
     * How many products one order may have discounted, or 0 for no limit.
     *
     * With a limit the code discounts products only - a domain is not one -
     * and when more are eligible than it allows, the highest-priced ones are
     * the ones discounted (pickLimited), so the result does not depend on the
     * order things were added to the basket.
     */
    public function productLimit(): int
    {
        return max(0, (int) $this->max_products);
    }

    /**
     * The lines a limited code discounts, out of the eligible ones: the
     * highest-priced first, the earlier line on a tie. The cart and the
     * invoice both choose here, so they cannot choose differently.
     *
     * @template T
     *
     * @param  array<int|string, T>  $eligible  the eligible lines, in basket order
     * @param  callable(T): float  $price  what a line costs
     * @return array<int|string, T> the chosen lines, keys kept
     */
    public function pickLimited(array $eligible, callable $price): array
    {
        $limit = $this->productLimit();

        if ($limit === 0 || count($eligible) <= $limit) {
            return $eligible;
        }

        $order = array_keys($eligible);
        $position = array_flip($order);

        usort($order, fn ($a, $b) => [$price($eligible[$b]), $position[$a]] <=> [$price($eligible[$a]), $position[$b]]);

        $chosen = array_flip(array_slice($order, 0, $limit));

        return array_filter($eligible, fn ($key) => isset($chosen[$key]), ARRAY_FILTER_USE_KEY);
    }

    /**
     * Whether the code waives the one-time setup fee rather than taking
     * something off the price.
     */
    public function givesFreeSetup(): bool
    {
        return $this->type === 'free_setup';
    }

    /** Whether a line billed over this period may carry the code. */
    public function coversCycle(?string $cycle): bool
    {
        $allowed = $this->billingCycles();

        return $allowed === [] || in_array(self::cycleKey($cycle), $allowed, true);
    }

    /** 'Semi-Annually', 'semiannually' and 'semi_annually' are one period. */
    public static function cycleKey(?string $cycle): string
    {
        return strtolower(str_replace([' ', '-', '_'], '', trim((string) $cycle)));
    }

    /** @return array<int, int> */
    public function coveredProductIds(): array
    {
        return self::idList($this->applies_to);
    }

    /** @return array<int, int> products that must all be in the basket */
    public function requiredProductIds(): array
    {
        return self::idList($this->requires);
    }

    /** @return array<int, int> */
    private static function idList(?string $value): array
    {
        $raw = trim((string) $value);

        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        $ids = is_array($decoded) ? $decoded : explode(',', $raw);

        return array_values(array_filter(array_map('intval', $ids)));
    }

    private function usedBy(Client $client, ?int $placingOrderId = null): bool
    {
        return Order::where('client_id', $client->id)
            ->when($placingOrderId, fn ($q) => $q->whereKeyNot($placingOrderId))
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
