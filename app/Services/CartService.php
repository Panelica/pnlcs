<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Client;
use App\Models\Domain;
use App\Models\DomainPricing;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductAddon;
use App\Models\Promotion;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class CartService
{
    public function getOrCreateCart(?int $clientId = null): Cart
    {
        $sessionId = session()->getId();

        if ($clientId) {
            $cart = Cart::where('user_id', $clientId)->first();
            if ($cart) {
                return $cart;
            }
        }

        $cart = Cart::where('session_id', $sessionId)->first();
        if ($cart) {
            if ($clientId && ! $cart->user_id) {
                $cart->update(['user_id' => $clientId]);
            }

            return $cart;
        }

        return Cart::create([
            'user_id' => $clientId,
            'session_id' => $sessionId,
            'data' => json_encode(['items' => [], 'promo_code' => null, 'currency_id' => null]),
        ]);
    }

    private function getData(Cart $cart): array
    {
        if (empty($cart->data)) {
            return ['items' => [], 'promo_code' => null, 'currency_id' => null];
        }
        $data = json_decode($cart->data, true);

        return array_merge(['items' => [], 'promo_code' => null, 'currency_id' => null], $data ?? []);
    }

    private function saveData(Cart $cart, array $data): Cart
    {
        $cart->data = json_encode($data);
        $cart->save();

        return $cart;
    }

    public function addProduct(Cart $cart, Product $product, string $billingCycle, ?string $domain = null, array $configOptions = [], ?string $notes = null, ?string $domainOption = null, array $addons = []): Cart
    {
        // The configure page refuses these and the listing leaves them out, but
        // the request that gets here only checked that the id exists — enough to
        // buy a discontinued plan from an old link, or a draft one nobody meant
        // to sell.
        if ($product->hidden || $product->retired) {
            throw ValidationException::withMessages([
                'product_id' => __('client.cart.product_unavailable'),
            ]);
        }

        if ($product->outOfStock()) {
            throw ValidationException::withMessages([
                'product_id' => __('client.cart.out_of_stock'),
            ]);
        }

        $price = $this->getProductPrice($product, $billingCycle);

        // The order form only offers cycles the product is priced for, but the
        // request accepted any cycle in the enum, so posting an unpriced one
        // bought the product for nothing.
        if ($price <= 0) {
            throw ValidationException::withMessages([
                'billing_cycle' => __('client.cart.cycle_unavailable'),
            ]);
        }

        // Configurable options are part of the recurring price. They used to be
        // stored on the cart item and never charged for.
        $optionService = app(ConfigOptionService::class);
        $normalised = $optionService->normalise($product, $configOptions, $billingCycle);
        $options = $optionService->toCartPayload($normalised);
        $price += $optionService->priceOf($normalised);

        $data = $this->getData($cart);

        // A register/transfer intent must survive into the order — fold it into
        // the notes so it reaches the provisioned service (OrderService copies
        // item notes onto the service record).
        if ($domainOption && $domainOption !== 'own' && $domain) {
            $intent = "[Domain {$domainOption} requested: {$domain}]";
            $notes = trim(($notes ? $notes."\n" : '').$intent);
        }

        // Addons keep their own price: they are billed as separate lines and
        // renew on their own dates.
        $addonService = app(AddonService::class);
        $addonPayload = $addonService->toCartPayload(
            $addonService->normalise($product, $addons, $billingCycle)
        );

        $data['items'][] = [
            'type' => 'product',
            'product_id' => $product->id,
            'addons' => $addonPayload,
            'product_name' => $product->name,
            'billing_cycle' => $billingCycle,
            'domain' => $domain,
            'domain_option' => $domainOption,
            'config_options' => $options,
            'price' => round($price, 2),
            'notes' => $notes,
        ];

        return $this->saveData($cart, $data);
    }

    /**
     * Add a domain registration/transfer to the cart.
     */
    public function addDomain(Cart $cart, string $domain, string $type = 'register', int $years = 1, ?string $eppCode = null): Cart
    {
        $tld = '.'.implode('.', array_slice(explode('.', $domain), 1));
        $pricing = DomainPricing::where('extension', $tld)->where('enabled', true)->first();

        if (! $pricing) {
            // Returning the cart untouched meant the controller reported success
            // and the customer checked out without the domain they asked for.
            throw ValidationException::withMessages([
                'domain' => __('client.cart.domain_tld_unsupported', ['tld' => $tld]),
            ]);
        }

        $minYears = max(1, (int) ($pricing->min_years ?: 1));
        $maxYears = max($minYears, (int) ($pricing->max_years ?: 10));

        if ($years < $minYears || $years > $maxYears) {
            throw ValidationException::withMessages([
                'years' => __('client.cart.domain_years_unsupported', [
                    'tld' => $tld,
                    'min' => $minYears,
                    'max' => $maxYears,
                ]),
            ]);
        }

        // The customer is buying a term, not a year: the order registers the
        // domain for all of it and the invoice line says so.
        $unit = ($type === 'transfer') ? $pricing->transfer_price : $pricing->register_price;
        $price = round((float) $unit * $years, 2);

        // What the next renewal will cost — the renewal rate for the same
        // term, not the introductory one that was paid to get the domain.
        $renewal = round((float) $pricing->renew_price * $years, 2);

        $data = $this->getData($cart);

        $data['items'][] = [
            'type' => 'domain',
            'domain' => $domain,
            'tld' => $tld,
            'action' => $type, // register | transfer
            'years' => $years,
            'epp_code' => $type === 'transfer' ? ($eppCode ?: null) : null,
            'price' => $price,
            'renewal_amount' => $renewal,
        ];

        return $this->saveData($cart, $data);
    }

    public function removeItem(Cart $cart, int $index): Cart
    {
        $data = $this->getData($cart);
        $items = $data['items'] ?? [];

        if (isset($items[$index])) {
            array_splice($items, $index, 1);
            $data['items'] = array_values($items);
        }

        return $this->saveData($cart, $data);
    }

    public function applyPromoCode(Cart $cart, string $code): array
    {
        $promo = Promotion::where('code', $code)->first();

        if (! $promo) {
            return ['success' => false, 'message' => 'Invalid promo code.', 'discount' => 0.0];
        }

        if (! $promo->isValidFor($this->cartClient($cart), $this->cartProductIds($cart))) {
            return ['success' => false, 'message' => 'This promo code cannot be used on this order.', 'discount' => 0.0];
        }

        $data = $this->getData($cart);
        $data['promo_code'] = $code;
        $this->saveData($cart, $data);

        $totals = $this->calculateTotal($cart);
        $discount = $totals['discount'];

        return ['success' => true, 'message' => 'Promo code applied successfully.', 'discount' => $discount];
    }

    public function calculateTotal(Cart $cart): array
    {
        $data = $this->getData($cart);
        $items = $data['items'] ?? [];
        $promoCode = $data['promo_code'] ?? null;

        // r147-linebased: quote what the invoice will charge.
        //
        // This applied the tax rate to the whole subtotal and knew nothing
        // about which lines carry tax, and it never mentioned the customer's
        // group discount - which the invoice applies as a line of its own. So
        // somebody buying a product marked not taxable was quoted tax that was
        // never charged, and somebody in a discount group was quoted the full
        // price and billed less. The order below is the invoice's order:
        // lines, then the group discount, then the promotion, then tax on what
        // is left of the taxable side.
        $taxFlags = $this->taxFlagsFor($items);

        $taxable = 0.0;
        $untaxed = 0.0;
        $enrichedItems = [];

        foreach ($items as $index => $item) {
            $price = (float) ($item['price'] ?? 0);
            $addons = $item['addons'] ?? [];

            $addonTotal = array_sum(array_map(fn ($a) => (float) ($a['price'] ?? 0), $addons));

            if ($taxFlags['items'][$index] ?? true) {
                $taxable += $price;
            } else {
                $untaxed += $price;
            }

            foreach ($addons as $addonIndex => $addon) {
                $addonPrice = (float) ($addon['price'] ?? 0);

                if ($taxFlags['addons'][$index][$addonIndex] ?? true) {
                    $taxable += $addonPrice;
                } else {
                    $untaxed += $addonPrice;
                }
            }

            $enrichedItems[] = array_merge($item, [
                'price' => $price,
                'addon_total' => round($addonTotal, 2),
                'line_total' => round($price + $addonTotal, 2),
            ]);
        }

        $subtotal = $taxable + $untaxed;

        // The group discount comes off each side separately, exactly as the
        // invoice writes it, so the taxable amount falls by the discount given
        // on taxable work and no more.
        $groupPercent = (float) ($this->cartClient($cart)?->group?->discount_percent ?? 0);
        $groupDiscount = 0.0;

        if ($groupPercent > 0) {
            $onTaxable = round($taxable * ($groupPercent / 100), 2);
            $onUntaxed = round($untaxed * ($groupPercent / 100), 2);

            $taxable -= $onTaxable;
            $untaxed -= $onUntaxed;
            $groupDiscount = $onTaxable + $onUntaxed;
        }

        $promoDiscount = 0.0;

        if ($promoCode) {
            $promo = Promotion::where('code', $promoCode)->first();

            if ($promo && $promo->isValidFor($this->cartClient($cart), $this->cartProductIds($cart))) {
                $discountable = $taxable + $untaxed;

                $promoDiscount = $promo->type === 'percentage'
                    ? round($discountable * ((float) $promo->value / 100), 2)
                    : min((float) $promo->value, $discountable);

                // The invoice writes the promotion as one line, taxed when
                // there is any taxable work on the order, so it comes off the
                // taxable side first.
                if ($taxable > 0) {
                    $taxable -= $promoDiscount;
                } else {
                    $untaxed -= $promoDiscount;
                }
            }
        }

        // carts.user_id holds the client id, despite the column name.
        $taxRate = $this->getTaxRate($cart->user_id);
        $taxAmount = round($taxable * ($taxRate / 100), 2);
        $total = round(max(0, $taxable + $untaxed) + $taxAmount, 2);

        return [
            'subtotal' => round($subtotal, 2),
            'discount' => round($groupDiscount + $promoDiscount, 2),
            'group_discount' => round($groupDiscount, 2),
            'promo_discount' => round($promoDiscount, 2),
            'tax' => $taxAmount,
            'tax_rate' => $taxRate,
            'total' => $total,
            'items' => $enrichedItems,
            'promo_code' => $promoCode,
        ];
    }

    /**
     * Which basket lines carry tax, read the same way the invoice reads them.
     *
     * A product line follows its product's flag; an addon follows its own; a
     * domain always carries tax, because a domain has no flag of its own and
     * the order writes it that way.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array{items: array<int, bool>, addons: array<int, array<int, bool>>}
     */
    private function taxFlagsFor(array $items): array
    {
        $productIds = [];
        $addonIds = [];

        foreach ($items as $item) {
            if (($item['type'] ?? 'product') !== 'domain' && ! empty($item['product_id'])) {
                $productIds[] = (int) $item['product_id'];
            }

            foreach ($item['addons'] ?? [] as $addon) {
                if (! empty($addon['addon_id'])) {
                    $addonIds[] = (int) $addon['addon_id'];
                }
            }
        }

        $productTax = $productIds
            ? Product::whereIn('id', array_unique($productIds))->pluck('tax', 'id')->all()
            : [];

        $addonTax = $addonIds
            ? ProductAddon::whereIn('id', array_unique($addonIds))->pluck('tax', 'id')->all()
            : [];

        $flags = ['items' => [], 'addons' => []];

        foreach ($items as $index => $item) {
            $flags['items'][$index] = ($item['type'] ?? 'product') === 'domain'
                ? true
                : (bool) ($productTax[(int) ($item['product_id'] ?? 0)] ?? true);

            foreach ($item['addons'] ?? [] as $addonIndex => $addon) {
                $flags['addons'][$index][$addonIndex] =
                    (bool) ($addonTax[(int) ($addon['addon_id'] ?? 0)] ?? true);
            }
        }

        return $flags;
    }

    /**
     * Turn the cart into an order.
     *
     * The order itself, its invoice, the services and the domains are all
     * created by OrderService, which is the single place that knows how an
     * order is put together. This method's job is to translate cart entries
     * into that shape.
     */
    public function checkout(Cart $cart, int $clientId, string $paymentMethod): Order
    {
        $data = $this->getData($cart);
        $client = Client::findOrFail($clientId);
        $items = [];

        foreach ($data['items'] ?? [] as $item) {
            if (($item['type'] ?? 'product') === 'domain') {
                $items[] = [
                    'type' => 'domain',
                    'domain' => $item['domain'],
                    'domain_type' => ($item['action'] ?? 'register') === 'transfer' ? 'Transfer' : 'Register',
                    'registration_period' => (int) ($item['years'] ?? 1),
                    'epp_code' => $item['epp_code'] ?? null,
                    'amount' => (float) ($item['price'] ?? 0),
                    'renewal_amount' => (float) ($item['renewal_amount'] ?? $item['price'] ?? 0),
                ];

                continue;
            }

            $items[] = [
                'type' => 'service',
                'product_id' => $item['product_id'],
                'domain' => $item['domain'] ?? '',
                'amount' => (float) ($item['price'] ?? 0),
                'billing_cycle' => $item['billing_cycle'] ?? 'Monthly',
                'notes' => $item['notes'] ?? null,
                'config_options' => $item['config_options'] ?? [],
                'addons' => $item['addons'] ?? [],
            ];
        }

        $order = app(OrderService::class)->processOrder(
            $client,
            $items,
            $paymentMethod,
            $data['promo_code'] ?? null
        );

        $this->clearCart($cart);

        return $order;
    }

    /** carts.user_id holds the client id, despite the column name. */
    private function cartClient(Cart $cart): ?Client
    {
        return $cart->user_id ? Client::find($cart->user_id) : null;
    }

    /** @return array<int, int> */
    private function cartProductIds(Cart $cart): array
    {
        $ids = [];

        foreach ($this->getData($cart)['items'] ?? [] as $item) {
            if (($item['type'] ?? 'product') !== 'domain' && ! empty($item['product_id'])) {
                $ids[] = (int) $item['product_id'];
            }
        }

        return $ids;
    }

    public function clearCart(Cart $cart): void
    {
        $this->saveData($cart, ['items' => [], 'promo_code' => null, 'currency_id' => null]);
    }

    /** Zero means the product is not sold on that cycle — addProduct refuses it. */
    public function getProductPrice(Product $product, string $billingCycle): float
    {
        return $product->priceFor($billingCycle) ?? 0.0;
    }

    public function getAvailableCycles(Product $product): array
    {
        $priced = $product->pricedCycles();

        $cycles = [];
        $labels = [
            'monthly' => 'Monthly',
            'quarterly' => 'Quarterly',
            'semiannually' => 'Semi-Annually',
            'annually' => 'Annually',
            'biennially' => 'Biennially',
            'triennially' => 'Triennially',
        ];

        foreach ($labels as $key => $label) {
            if (isset($priced[$key])) {
                $cycles[] = ['label' => $label, 'price' => $priced[$key]];
            }
        }

        return $cycles;
    }

    /**
     * The rate the invoice will actually use, so the cart quotes the figure the
     * customer ends up being charged.
     */
    private function getTaxRate(?int $clientId = null): float
    {
        return (float) app(InvoiceService::class)->calculateTax(0.0, $clientId)['tax_rate'];
    }

    private function getNextDueDate(string $billingCycle): Carbon
    {
        return match ($billingCycle) {
            'monthly' => now()->addMonth(),
            'quarterly' => now()->addMonths(3),
            'semiannually' => now()->addMonths(6),
            'annually' => now()->addYear(),
            'biennially' => now()->addYears(2),
            'triennially' => now()->addYears(3),
            default => now()->addMonth(),
        };
    }
}
