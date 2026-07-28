<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Services\AddonService;
use App\Services\CartService;
use App\Services\ConfigOptionService;
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

        $cycles = $this->cartService->getAvailableCycles($product);
        $currency = Currency::getDefault();
        $optionGroups = app(ConfigOptionService::class)->groupsFor($product);
        $addons = app(AddonService::class)->availableFor($product);

        return view('client.cart.configure', compact('product', 'cycles', 'currency', 'optionGroups', 'addons'));
    }

    public function index()
    {
        $clientId = $this->getClientId();
        $cart = $this->cartService->getOrCreateCart($clientId);
        $totals = $this->cartService->calculateTotal($cart);
        $currency = Currency::getDefault();

        return view('client.cart.index', compact('cart', 'totals', 'currency'));
    }

    public function addToCart(Request $request)
    {
        $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'billing_cycle' => 'required|string|in:monthly,quarterly,semiannually,annually,biennially,triennially',
            'domain' => 'nullable|string|max:255',
            'domain_option' => 'nullable|string|in:register,transfer,own',
            'notes' => 'nullable|string|max:2000',
            'config_options' => 'nullable|array',
            'addons' => 'nullable|array',
            'addons.*' => 'integer',
        ]);

        $product = Product::findOrFail($request->product_id);
        $clientId = $this->getClientId();
        $cart = $this->cartService->getOrCreateCart($clientId);

        $this->cartService->addProduct(
            $cart,
            $product,
            $request->billing_cycle,
            $request->domain,
            $request->input('config_options', []),
            $request->input('notes'),
            $request->input('domain_option'),
            $request->input('addons', [])
        );

        return redirect()->route('client.cart.index')
            ->with('success', __('messages.success.product_added_to_cart'));
    }

    /**
     * Add a domain to the cart (from domain-search page).
     */
    public function addDomainToCart(Request $request)
    {
        $request->validate([
            'domain' => 'required|string|max:253',
            'type' => 'required|string|in:register,transfer',
            'years' => 'integer|min:1|max:10',
        ]);

        $domain = strtolower(trim($request->domain));
        $type = $request->type;
        $years = (int) ($request->years ?? 1);
        $clientId = $this->getClientId();
        $cart = $this->cartService->getOrCreateCart($clientId);

        $updatedCart = $this->cartService->addDomain($cart, $domain, $type, $years);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => __('messages.success.domain_added_to_cart', ['type' => ucfirst($type), 'domain' => $domain])]);
        }

        return redirect()->route('client.cart.index')
            ->with('success', __('messages.success.domain_added_to_cart', ['type' => ucfirst($type), 'domain' => $domain]));
    }

    public function removeItem(Request $request, int $index)
    {
        $clientId = $this->getClientId();
        $cart = $this->cartService->getOrCreateCart($clientId);
        $this->cartService->removeItem($cart, $index);

        return redirect()->route('client.cart.index')
            ->with('success', __('messages.success.item_removed_from_cart'));
    }

    public function applyPromo(Request $request)
    {
        $request->validate(['code' => 'required|string|max:50']);

        $clientId = $this->getClientId();
        $cart = $this->cartService->getOrCreateCart($clientId);
        $result = $this->cartService->applyPromoCode($cart, $request->code);

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
        $cart = $this->cartService->getOrCreateCart($clientId);
        $totals = $this->cartService->calculateTotal($cart);

        if (empty($totals['items'])) {
            return redirect()->route('client.cart.index')
                ->with('error', __('messages.error.cart_is_empty'));
        }

        $currency = Currency::getDefault();
        $paymentMethods = $this->getAvailablePaymentMethods();

        return view('client.cart.checkout', compact('cart', 'totals', 'currency', 'paymentMethods'));
    }

    public function processCheckout(Request $request)
    {
        $request->validate([
            'payment_method' => 'required|string',
            'terms' => 'accepted',
        ]);

        $clientId = $this->getClientId();

        if (! $clientId) {
            return redirect()->route('client.login')
                ->with('error', __('messages.error.login_required'));
        }

        $cart = $this->cartService->getOrCreateCart($clientId);
        $totals = $this->cartService->calculateTotal($cart);

        if (empty($totals['items'])) {
            return redirect()->route('client.cart.index')
                ->with('error', __('messages.error.cart_is_empty'));
        }

        $order = $this->cartService->checkout($cart, $clientId, $request->payment_method);

        return redirect()->route('client.invoices.show', $order->invoice_id)
            ->with('success', __('messages.success.order_placed', ['num' => $order->order_num]));
    }

    private function getClientId(): ?int
    {
        $user = auth()->user();

        return $user ? $user->clients()->first()?->id : null;
    }

    private function getAvailablePaymentMethods(): array
    {
        return [
            'banktransfer' => __('messages.payment_method.bank_transfer'),
            'paypal' => __('messages.payment_method.paypal'),
            'stripe' => __('messages.payment_method.credit_debit_card'),
        ];
    }
}
