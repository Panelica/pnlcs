<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Currency;
use App\Models\Domain;
use App\Models\DomainPricing;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Service;
use App\Models\TaxRule;
use Carbon\Carbon;
use Illuminate\Support\Str;

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

    public function addProduct(Cart $cart, Product $product, string $billingCycle, ?string $domain = null, array $configOptions = [], ?string $notes = null, ?string $domainOption = null): Cart
    {
        $price = $this->getProductPrice($product, $billingCycle);
        $data = $this->getData($cart);

        // A register/transfer intent must survive into the order — fold it into
        // the notes so it reaches the provisioned service (OrderService copies
        // item notes onto the service record).
        if ($domainOption && $domainOption !== 'own' && $domain) {
            $intent = "[Domain {$domainOption} requested: {$domain}]";
            $notes = trim(($notes ? $notes."\n" : '').$intent);
        }

        $data['items'][] = [
            'type' => 'product',
            'product_id' => $product->id,
            'product_name' => $product->name,
            'billing_cycle' => $billingCycle,
            'domain' => $domain,
            'domain_option' => $domainOption,
            'config_options' => $configOptions,
            'price' => $price,
            'notes' => $notes,
        ];

        return $this->saveData($cart, $data);
    }

    /**
     * Add a domain registration/transfer to the cart.
     */
    public function addDomain(Cart $cart, string $domain, string $type = 'register', int $years = 1): Cart
    {
        $tld = '.'.implode('.', array_slice(explode('.', $domain), 1));
        $pricing = DomainPricing::where('extension', $tld)->where('enabled', true)->first();

        if (! $pricing) {
            return $cart; // TLD not supported
        }

        $price = ($type === 'transfer') ? $pricing->transfer_price : $pricing->register_price;
        $data = $this->getData($cart);

        $data['items'][] = [
            'type' => 'domain',
            'domain' => $domain,
            'tld' => $tld,
            'action' => $type, // register | transfer
            'years' => $years,
            'price' => $price,
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

        if (! $promo->isValid()) {
            return ['success' => false, 'message' => 'This promo code has expired or reached its usage limit.', 'discount' => 0.0];
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

        $subtotal = 0.0;
        $enrichedItems = [];

        foreach ($items as $item) {
            $price = (float) ($item['price'] ?? 0);
            $subtotal += $price;
            $enrichedItems[] = array_merge($item, ['price' => $price]);
        }

        $discount = 0.0;
        if ($promoCode) {
            $promo = Promotion::where('code', $promoCode)->first();
            if ($promo && $promo->isValid()) {
                if ($promo->type === 'percentage') {
                    $discount = round($subtotal * ((float) $promo->value / 100), 2);
                } else {
                    $discount = min((float) $promo->value, $subtotal);
                }
            }
        }

        $taxableAmount = $subtotal - $discount;
        $taxRate = $this->getTaxRate();
        $taxAmount = round($taxableAmount * ($taxRate / 100), 2);
        $total = round($taxableAmount + $taxAmount, 2);

        return [
            'subtotal' => round($subtotal, 2),
            'discount' => round($discount, 2),
            'tax' => $taxAmount,
            'tax_rate' => $taxRate,
            'total' => $total,
            'items' => $enrichedItems,
            'promo_code' => $promoCode,
        ];
    }

    public function checkout(Cart $cart, int $clientId, string $paymentMethod): Order
    {
        $totals = $this->calculateTotal($cart);

        $order = Order::create([
            'order_num' => strtoupper(Str::random(8)),
            'client_id' => $clientId,
            'date' => now(),
            'promo_code' => $totals['promo_code'],
            'amount' => $totals['total'],
            'payment_method' => $paymentMethod,
            'status' => 'pending',
            'ip_address' => request()->ip(),
        ]);

        $invoice = Invoice::create([
            'client_id' => $clientId,
            'invoice_num' => 'INV-'.strtoupper(Str::random(8)),
            'date' => now(),
            'due_date' => now()->addDays(7),
            'subtotal' => $totals['subtotal'],
            'tax' => $totals['tax'],
            'total' => $totals['total'],
            'tax_rate' => $totals['tax_rate'],
            'status' => 'unpaid',
            'payment_method' => $paymentMethod,
        ]);

        $order->update(['invoice_id' => $invoice->id]);

        foreach ($totals['items'] as $item) {
            $itemType = $item['type'] ?? 'product';

            if ($itemType === 'domain') {
                // Create domain record
                $domain = Domain::create([
                    'client_id' => $clientId,
                    'order_id' => $order->id,
                    'type' => $item['action'] === 'transfer' ? 'Transfer' : 'Register',
                    'domain' => $item['domain'],
                    'registrar' => 'Manual',
                    'registration_period' => $item['years'] ?? 1,
                    'status' => 'pending',
                    'payment_method' => $paymentMethod,
                    'first_payment_amount' => $item['price'],
                    'recurring_amount' => $item['price'],
                ]);

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'client_id' => $clientId,
                    'type' => 'Domain',
                    'rel_id' => $domain->id,
                    'description' => ucfirst($item['action']).' '.$item['domain'].' — '.($item['years'] ?? 1).' Year(s)',
                    'amount' => $item['price'],
                    'taxed' => true,
                    'due_date' => now()->addDays(7),
                ]);
            } else {
                // Existing product handling
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'client_id' => $clientId,
                    'type' => 'Hosting',
                    'rel_id' => $item['product_id'],
                    'description' => $item['product_name'].' — '.ucfirst($item['billing_cycle']),
                    'amount' => $item['price'],
                    'taxed' => true,
                    'due_date' => now()->addDays(7),
                ]);

                Service::create([
                    'client_id' => $clientId,
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'domain' => $item['domain'] ?? '',
                    'payment_method' => $paymentMethod,
                    'amount' => $item['price'],
                    'billing_cycle' => $item['billing_cycle'],
                    'next_due_date' => $this->getNextDueDate($item['billing_cycle']),
                    'registration_date' => now(),
                    'status' => 'pending',
                    'first_payment_amount' => $item['price'],
                    'notes' => $item['notes'] ?? null,
                ]);
            }
        }

        $this->clearCart($cart);

        return $order;
    }

    public function clearCart(Cart $cart): void
    {
        $this->saveData($cart, ['items' => [], 'promo_code' => null, 'currency_id' => null]);
    }

    public function getProductPrice(Product $product, string $billingCycle): float
    {
        $currency = Currency::getDefault();
        $currencyId = $currency ? $currency->id : 1;

        $pricing = Pricing::where('type', 'product')
            ->where('rel_id', $product->id)
            ->where('currency_id', $currencyId)
            ->first();

        if (! $pricing) {
            $pricing = Pricing::where('type', 'product')
                ->where('rel_id', $product->id)
                ->first();
        }

        if (! $pricing) {
            return 0.0;
        }

        $cycle = $billingCycle;
        if (isset($pricing->{$cycle}) && $pricing->{$cycle} !== null) {
            return (float) $pricing->{$cycle};
        }

        foreach (['monthly', 'quarterly', 'semiannually', 'annually', 'biennially', 'triennially'] as $fallback) {
            if (isset($pricing->{$fallback}) && (float) $pricing->{$fallback} > 0) {
                return (float) $pricing->{$fallback};
            }
        }

        return 0.0;
    }

    public function getAvailableCycles(Product $product): array
    {
        $currency = Currency::getDefault();
        $currencyId = $currency ? $currency->id : 1;

        $pricing = Pricing::where('type', 'product')
            ->where('rel_id', $product->id)
            ->where('currency_id', $currencyId)
            ->first();

        if (! $pricing) {
            $pricing = Pricing::where('type', 'product')
                ->where('rel_id', $product->id)
                ->first();
        }

        if (! $pricing) {
            return [];
        }

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
            if (isset($pricing->{$key}) && (float) $pricing->{$key} > 0) {
                $cycles[] = ['label' => $label, 'price' => (float) $pricing->{$key}];
            }
        }

        return $cycles;
    }

    private function getTaxRate(): float
    {
        $rule = TaxRule::where('level', 1)->first();

        return $rule ? (float) $rule->tax_rate : 0.0;
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
