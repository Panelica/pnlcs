<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Services\CartService;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private CartService $cartService) {}

    public function store()
    {
        $groups = ProductGroup::where('hidden', false)
            ->with(['products' => function ($q) {
                $q->active()->with('pricing')->orderBy('sort_order');
            }])
            ->orderBy('sort_order')
            ->get();

        $currency = Currency::getDefault();

        return view('client.cart.store', compact('groups', 'currency'));
    }

    public function configure(Request $request, Product $product)
    {
        if ($product->hidden || $product->retired) {
            abort(404);
        }

        $cycles   = $this->cartService->getAvailableCycles($product);
        $currency = Currency::getDefault();

        return view('client.cart.configure', compact('product', 'cycles', 'currency'));
    }

    public function index()
    {
        $clientId = $this->getClientId();
        $cart     = $this->cartService->getOrCreateCart($clientId);
        $totals   = $this->cartService->calculateTotal($cart);
        $currency = Currency::getDefault();

        return view('client.cart.index', compact('cart', 'totals', 'currency'));
    }

    public function addToCart(Request $request)
    {
        $request->validate([
            'product_id'    => 'required|integer|exists:products,id',
            'billing_cycle' => 'required|string|in:monthly,quarterly,semiannually,annually,biennially,triennially',
            'domain'        => 'nullable|string|max:255',
        ]);

        $product  = Product::findOrFail($request->product_id);
        $clientId = $this->getClientId();
        $cart     = $this->cartService->getOrCreateCart($clientId);

        $this->cartService->addProduct(
            $cart,
            $product,
            $request->billing_cycle,
            $request->domain,
            $request->input('config_options', [])
        );

        return redirect()->route('client.cart.index')
            ->with('success', 'Product added to cart successfully.');
    }

    public function removeItem(Request $request, int $index)
    {
        $clientId = $this->getClientId();
        $cart     = $this->cartService->getOrCreateCart($clientId);
        $this->cartService->removeItem($cart, $index);

        return redirect()->route('client.cart.index')
            ->with('success', 'Item removed from cart.');
    }

    public function applyPromo(Request $request)
    {
        $request->validate(['code' => 'required|string|max:50']);

        $clientId = $this->getClientId();
        $cart     = $this->cartService->getOrCreateCart($clientId);
        $result   = $this->cartService->applyPromoCode($cart, $request->code);

        if ($result['success']) {
            return redirect()->route('client.cart.index')
                ->with('success', $result['message']);
        }

        return redirect()->route('client.cart.index')
            ->with('error', $result['message']);
    }

    public function checkout()
    {
        $clientId = $this->getClientId();
        $cart     = $this->cartService->getOrCreateCart($clientId);
        $totals   = $this->cartService->calculateTotal($cart);

        if (empty($totals['items'])) {
            return redirect()->route('client.cart.index')
                ->with('error', 'Your cart is empty.');
        }

        $currency       = Currency::getDefault();
        $paymentMethods = $this->getAvailablePaymentMethods();

        return view('client.cart.checkout', compact('cart', 'totals', 'currency', 'paymentMethods'));
    }

    public function processCheckout(Request $request)
    {
        $request->validate([
            'payment_method' => 'required|string',
            'terms'          => 'accepted',
        ]);

        $clientId = $this->getClientId();

        if (!$clientId) {
            return redirect()->route('client.login')
                ->with('error', 'Please log in to complete your order.');
        }

        $cart = $this->cartService->getOrCreateCart($clientId);

        $totals = $this->cartService->calculateTotal($cart);
        if (empty($totals['items'])) {
            return redirect()->route('client.cart.index')
                ->with('error', 'Your cart is empty.');
        }

        $order = $this->cartService->checkout($cart, $clientId, $request->payment_method);

        return redirect()->route('client.invoices.show', $order->invoice_id)
            ->with('success', 'Order #' . $order->order_num . ' placed successfully. Please complete payment.');
    }

    private function getClientId(): ?int
    {
        $user = auth()->user();
        return $user ? $user->clients()->first()?->id : null;
    }

    private function getAvailablePaymentMethods(): array
    {
        return [
            'banktransfer' => 'Bank Transfer',
            'paypal'       => 'PayPal',
            'stripe'       => 'Credit / Debit Card',
        ];
    }
}
