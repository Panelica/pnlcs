<?php

namespace App\Http\Controllers\Client;

use App\Enums\ClientStatus;
use App\Http\Controllers\Concerns\ResolvesClient;
use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Domain;
use App\Models\GatewaySettings;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Services\AddonService;
use App\Services\CartService;
use App\Services\ConfigOptionService;
use App\Services\Module\ModuleRegistry;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CartController extends Controller
{
    use ResolvesClient;

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
            // The same reading the search box gives it: a customer pastes an
            // address, and it used to be stored exactly as pasted.
            Domain::normalise($request->domain) ?: null,
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

        $domain = Domain::normalise($request->domain);
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
        $client = $this->currentClient();

        // Closing or suspending an account should stop new business; the
        // status was set on the admin screen and read by nothing.
        if (! $client || $client->status !== ClientStatus::Active) {
            return back()->withErrors(['payment_method' => __('client.cart.account_not_active')]);
        }

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
        // Only what the customer was actually offered: anything else ends up
        // written onto the order and the service as a gateway nobody can
        // refund through.
        $request->validate([
            'payment_method' => ['required', 'string', Rule::in(array_keys($this->getAvailablePaymentMethods()))],
            'terms' => 'accepted',
        ]);

        $clientId = $this->getClientId();

        if (! $clientId) {
            return redirect()->route('client.login')
                ->with('error', __('messages.error.login_required'));
        }

        $client = $this->currentClient();

        // Closing or suspending an account should stop new business; the
        // status was set on the admin screen and read by nothing.
        if (! $client || $client->status !== ClientStatus::Active) {
            return back()->withErrors(['payment_method' => __('client.cart.account_not_active')]);
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

    /**
     * The gateways the operator has switched on.
     *
     * This used to be a fixed list of three, so a customer could pick a card
     * payment on an installation where no card gateway was configured and be
     * quietly handed a bank transfer at the next step.
     */
    private function getAvailablePaymentMethods(): array
    {
        $registry = app(ModuleRegistry::class);

        $active = GatewaySettings::where('setting', 'active')
            ->get()
            ->filter(fn ($row) => (string) $row->value === '1')
            ->pluck('gateway')
            ->unique();

        if ($active->isEmpty()) {
            return ['banktransfer' => __('messages.payment_method.bank_transfer')];
        }

        return $active->mapWithKeys(fn (string $name) => [
            $name => $registry->getGatewayModule($name)?->getModuleName() ?? ucfirst($name),
        ])->all();
    }
}
