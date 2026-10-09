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
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class CartService
{
    /**
     * The account's cart, or the visitor's.
     *
     * A guest cart is remembered IN the session data, not keyed by the session
     * id: Laravel rotates the id on login and carries the data across, so a
     * cart tied to the id evaporated at the exact moment its owner appeared.
     * Tied to the data, it survives the rotation and simply changes hands.
     */
    public function getOrCreateCart(?int $clientId = null): Cart
    {
        if ($clientId) {
            $cart = Cart::where('user_id', $clientId)->first();
            if ($cart) {
                return $cart;
            }
        }

        $guestCartId = session('guest_cart_id');
        if ($guestCartId) {
            // Only a cart that still belongs to nobody, or to this very
            // account: a stale id pointing at someone else's cart - a shared
            // machine, an old session - must not hand their basket over.
            $cart = Cart::whereKey($guestCartId)
                ->where(fn ($q) => $q->whereNull('user_id')->when($clientId, fn ($q2) => $q2->orWhere('user_id', $clientId)))
                ->first();
            if ($cart) {
                if ($clientId && ! $cart->user_id) {
                    $cart->update(['user_id' => $clientId]);
                    session()->forget('guest_cart_id');
                }

                return $cart;
            }
            session()->forget('guest_cart_id');
        }

        $cart = Cart::create([
            'user_id' => $clientId,
            'session_id' => session()->getId(),
            'data' => json_encode(['items' => [], 'promo_code' => null, 'currency_id' => null]),
        ]);

        if (! $clientId) {
            session(['guest_cart_id' => $cart->id]);
        }

        return $cart;
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

    public function addProduct(Cart $cart, Product $product, string $billingCycle, ?string $domain = null, array $configOptions = [], ?string $notes = null, ?string $domainOption = null, array $addons = [], ?string $appSlug = null, ?string $sshKeys = null, array $customFields = []): Cart
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

        // A hosting account has to serve something: an order without a domain
        // provisioned as an account with no site, and the admin could only
        // cancel it. The form always asks for one; the request is not the form.
        if ($product->type === 'hosting' && $product->show_domain_options && trim((string) $domain) === '') {
            throw ValidationException::withMessages([
                'domain' => __('client.cart.domain_required'),
            ]);
        }

        // A free product is one per customer. There is no per-client purchase
        // limit anywhere in the product schema, so it is enforced here: a
        // second copy in the same cart, or an order from a client that already
        // holds a live service of it, is refused.
        if ($product->pay_type === 'free') {
            foreach (($this->getData($cart)['items'] ?? []) as $existing) {
                if (($existing['type'] ?? 'product') !== 'domain'
                    && (int) ($existing['product_id'] ?? 0) === (int) $product->id) {
                    throw ValidationException::withMessages([
                        'product_id' => __('client.cart.free_limit_reached'),
                    ]);
                }
            }
            if ($cart->user_id && Service::where('client_id', $cart->user_id)
                    ->where('product_id', $product->id)
                    ->whereNotIn('status', ['terminated', 'cancelled'])
                    ->exists()) {
                throw ValidationException::withMessages([
                    'product_id' => __('client.cart.free_limit_reached'),
                ]);
            }
        }

        $price = $this->getProductPrice($product, $billingCycle);

        // The order form only offers cycles the product is priced for, but the
        // request accepted any cycle in the enum, so posting an unpriced one
        // bought the product for nothing. A free product's zero is a real
        // price, but only on the cycles it is actually offered on.
        if ($product->priceFor($billingCycle) === null || ($price <= 0 && $product->pay_type !== 'free')) {
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
            // The app this order installs, when the product lets the customer
            // choose one rather than selling a fixed app.
            'app_slug' => $appSlug,
            // SSH keys for a virtual server, when the customer gave any.
            'ssh_keys' => $sshKeys,
            // Answers to the product's own questions, keyed by field id.
            'custom_fields' => $customFields,
            'price' => round($price, 2),
            'notes' => $notes,
        ];

        return $this->saveData($cart, $data);
    }

    /**
     * Can this name be had, and what does a year of it cost?
     *
     * Hosting ordered with "register a new domain" or "transfer" used to note
     * the name on the service and nothing else: it was never checked, never
     * priced and never registered, so the customer paid for the hosting alone
     * believing the domain came with it (GitHub issue #48). The configure page
     * asks this to show the price in the summary, and the cart asks it again
     * before adding the domain, because the request is not the page.
     *
     * status: ok, taken (register: someone has it), unchecked (the registry did
     * not answer - never read as free), not_registered (transfer: there is
     * nothing to transfer), tld_unsupported (the shop does not sell it).
     *
     * @return array{domain: string, type: string, status: string, price: float}
     */
    public function quoteDomain(string $domain, string $type): array
    {
        $type = $type === 'transfer' ? 'transfer' : 'register';
        $tld = '.'.implode('.', array_slice(explode('.', $domain), 1));
        $pricing = str_contains($domain, '.')
            ? DomainPricing::where('extension', $tld)->where('enabled', true)->first()
            : null;

        $quote = ['domain' => $domain, 'type' => $type, 'tld' => $tld, 'status' => 'tld_unsupported', 'price' => 0.0];
        if (! $pricing) {
            return $quote;
        }

        $quote['price'] = round((float) ($type === 'transfer' ? $pricing->transfer_price : $pricing->register_price), 2);
        $lookup = app(DomainAvailability::class)->check($domain);

        if ($type === 'register') {
            $quote['status'] = ! $lookup['checked'] ? 'unchecked' : ($lookup['available'] ? 'ok' : 'taken');
        } else {
            // A transfer needs a registered name; if the registry could not
            // say, the registrar is the one who will find out.
            $quote['status'] = ($lookup['checked'] && $lookup['available']) ? 'not_registered' : 'ok';
        }

        return $quote;
    }

    /** What to tell the customer about a quote that is not ok. */
    public function domainQuoteProblem(array $quote): ?string
    {
        return match ($quote['status']) {
            'taken' => __('client.cart.domain_taken', ['domain' => $quote['domain']]),
            'unchecked' => __('client.cart.domain_unchecked', ['domain' => $quote['domain']]),
            'not_registered' => __('client.cart.domain_not_registered', ['domain' => $quote['domain']]),
            'tld_unsupported' => __('client.cart.domain_tld_unsupported', ['tld' => $quote['tld']]),
            default => null,
        };
    }

    public function hasDomain(Cart $cart, string $domain): bool
    {
        foreach ($this->getData($cart)['items'] ?? [] as $item) {
            if (($item['type'] ?? '') === 'domain' && strcasecmp((string) ($item['domain'] ?? ''), $domain) === 0) {
                return true;
            }
        }

        return false;
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

        // WHOIS privacy, when the extension offers it: free privacy is on
        // unless the customer turns it off, paid privacy is off until they
        // add it (setDomainPrivacy()).
        $privacyYear = $pricing->privacy_price;
        $privacy = $privacyYear !== null && (float) $privacyYear <= 0;

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
            'base_price' => $price,
            'base_renewal' => $renewal,
            'privacy_price' => $privacyYear !== null ? round((float) $privacyYear, 2) : null,
            'privacy' => $privacy,
        ];

        return $this->saveData($cart, $data);
    }

    /**
     * Make the first year of a domain in the cart free, because it was ordered
     * with a product that gives one (Product::givesFreeDomain()). Only the
     * registration or transfer is free; the renewal stays at its price. Once
     * per domain line.
     */
    public function makeDomainFree(Cart $cart, string $domain, int $productId): Cart
    {
        $data = $this->getData($cart);

        foreach ($data['items'] as $i => $item) {
            if (($item['type'] ?? '') !== 'domain' || strcasecmp((string) ($item['domain'] ?? ''), $domain) !== 0 || ! empty($item['free_with'])) {
                continue;
            }
            $years = max(1, (int) ($item['years'] ?? 1));
            $unit = round((float) ($item['base_price'] ?? $item['price'] ?? 0) / $years, 2);
            $data['items'][$i]['price'] = round(max(0, (float) ($item['price'] ?? 0) - $unit), 2);
            $data['items'][$i]['base_price'] = round(max(0, (float) ($item['base_price'] ?? 0) - $unit), 2);
            $data['items'][$i]['free_with'] = $productId;
        }

        return $this->saveData($cart, $data);
    }

    /**
     * Take a line out of the cart.
     *
     * Hosting ordered with "register a new domain" or "transfer" and the
     * domain line that came with it belong together (GitHub issue #48). The
     * domain line could be removed on its own, and the order then went
     * through as hosting set up for a name the customer would never own. So
     * that line cannot be removed by itself, and removing the hosting takes
     * it along. A domain bought on its own is removed as before.
     *
     * @return array{removed: bool, message: ?string}
     */
    /**
     * Add or drop WHOIS privacy on a domain line, at the extension's price
     * for every year of the term, on the first payment and on renewals.
     */
    public function setDomainPrivacy(Cart $cart, int $index, bool $on): bool
    {
        $data = $this->getData($cart);
        $item = $data['items'][$index] ?? null;

        if (! $item || ($item['type'] ?? '') !== 'domain' || ! array_key_exists('privacy_price', $item) || $item['privacy_price'] === null) {
            return false;
        }

        $extra = $on ? round((float) $item['privacy_price'] * max(1, (int) ($item['years'] ?? 1)), 2) : 0.0;
        $data['items'][$index]['privacy'] = $on;
        $data['items'][$index]['price'] = round((float) ($item['base_price'] ?? $item['price']) + $extra, 2);
        $data['items'][$index]['renewal_amount'] = round((float) ($item['base_renewal'] ?? $item['renewal_amount'] ?? 0) + $extra, 2);
        $this->saveData($cart, $data);

        return true;
    }

    public function removeItem(Cart $cart, int $index): array
    {
        $data = $this->getData($cart);
        $items = $data['items'] ?? [];

        if (! isset($items[$index])) {
            return ['removed' => false, 'message' => null];
        }

        $line = $items[$index];
        $hosting = $this->hostingFor($items, $line);

        if ($hosting !== null) {
            return ['removed' => false, 'message' => __('client.cart.domain_comes_with_hosting', [
                'domain' => $line['domain'] ?? '',
                'product' => $items[$hosting]['product_name'] ?? '',
            ])];
        }

        $remove = [$index];
        if ($this->buysDomain($line)) {
            foreach ($items as $i => $item) {
                if ($i !== $index && ($item['type'] ?? '') === 'domain'
                    && strcasecmp((string) ($item['domain'] ?? ''), (string) ($line['domain'] ?? '')) === 0) {
                    $remove[] = $i;
                }
            }
        }

        $data['items'] = array_values(array_diff_key($items, array_flip($remove)));
        $this->saveData($cart, $data);

        return ['removed' => true, 'message' => count($remove) > 1
            ? __('client.cart.removed_with_domain', ['domain' => $line['domain'] ?? ''])
            : null];
    }

    /** The index of the hosting line a domain line came with, or null when it was bought on its own. */
    private function hostingFor(array $items, array $line): ?int
    {
        if (($line['type'] ?? '') !== 'domain') {
            return null;
        }

        foreach ($items as $i => $item) {
            if ($this->buysDomain($item)
                && strcasecmp((string) ($item['domain'] ?? ''), (string) ($line['domain'] ?? '')) === 0) {
                return $i;
            }
        }

        return null;
    }

    /** A hosting line that orders its domain (register or transfer) along with it. */
    private function buysDomain(array $item): bool
    {
        return ($item['type'] ?? '') === 'product'
            && in_array($item['domain_option'] ?? null, ['register', 'transfer'], true)
            && trim((string) ($item['domain'] ?? '')) !== '';
    }

    /**
     * Apply the code a promotion link left in the session (PromoLink), once
     * the cart holds something it is for. A code the customer typed is never
     * replaced, and one that does not fit yet waits for the product it is
     * for.
     */
    public function applyPendingPromo(Cart $cart): void
    {
        $code = session(\App\Http\Middleware\PromoLink::SESSION);
        $data = $this->getData($cart);

        if (! is_string($code) || empty($data['items'])) {
            return;
        }

        if (! empty($data['promo_code'])) {
            session()->forget(\App\Http\Middleware\PromoLink::SESSION);

            return;
        }

        if ($this->applyPromoCode($cart, $code)['success']) {
            session()->forget(\App\Http\Middleware\PromoLink::SESSION);
        }
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

            $hosting = $this->hostingFor($items, $item);

            $enrichedItems[] = array_merge($item, [
                'price' => $price,
                'addon_total' => round($addonTotal, 2),
                'line_total' => round($price + $addonTotal, 2),
                // The hosting this domain line came with: it goes with that line.
                'comes_with' => $hosting !== null ? ($items[$hosting]['product_name'] ?? '') : null,
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

                // A code limited to some products comes off those lines only,
                // exactly as the invoice writes it. The cart used to take the
                // percentage off the whole basket, so the customer was quoted
                // 50 off and billed 5 off.
                $covered = $promo->coveredProductIds();

                if ($covered !== []) {
                    $coveredAmount = 0.0;

                    foreach ($items as $item) {
                        if (($item['type'] ?? 'product') !== 'domain' && in_array((int) ($item['product_id'] ?? 0), $covered, true)) {
                            $coveredAmount += (float) ($item['price'] ?? 0);
                        }
                    }

                    $discountable = min($coveredAmount, $discountable);
                }

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
                    'id_protection' => ! empty($item['privacy']),
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
                // The app the customer picked while ordering. Dropped here once,
                // which meant the order was placed for "WordPress" and
                // provisioned as an empty account: the cart knew, and nothing
                // downstream was told.
                'app_slug' => $item['app_slug'] ?? null,
                'ssh_keys' => $item['ssh_keys'] ?? null,
                'custom_fields' => $item['custom_fields'] ?? [],
            ];
        }

        // The cart-time check cannot see a guest's history - their account is
        // only created at checkout - so the one-per-customer rule for free
        // products is enforced once more here, where the client is known.
        foreach ($items as $checkItem) {
            if (($checkItem['type'] ?? '') !== 'service') {
                continue;
            }
            $checkProduct = Product::find($checkItem['product_id']);
            if ($checkProduct && $checkProduct->pay_type === 'free'
                && Service::where('client_id', $clientId)
                    ->where('product_id', $checkProduct->id)
                    ->whereNotIn('status', ['terminated', 'cancelled'])
                    ->exists()) {
                throw ValidationException::withMessages([
                    'product_id' => __('client.cart.free_limit_reached'),
                ]);
            }
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
