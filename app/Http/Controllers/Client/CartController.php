<?php

namespace App\Http\Controllers\Client;

use App\Enums\ClientStatus;
use App\Http\Controllers\Concerns\ResolvesClient;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Domain;
use App\Models\GatewaySettings;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Services\AddonService;
use App\Services\CartService;
use App\Services\ConfigOptionService;
use App\Models\DockerApp;
use App\Models\Server;
use App\Services\Module\ModuleRegistry;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    use ResolvesClient;

    public function __construct(private CartService $cartService) {}


    /**
     * The client when there is one, null for a visitor. The cart has always
     * been able to belong to a bare session; these pages now let it.
     */
    private function optionalClientId(): ?int
    {
        if (! auth()->check()) {
            return null;
        }

        return $this->getClientId() ?: null;
    }

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

        // Products that sell "pick your app" show the catalogue on the order
        // form; every other product is unaffected and pays for nothing.
        $apps = $this->productLetsCustomerPickApp($product) ? $this->sellableApps($product) : [];

        return view('client.cart.configure', compact('product', 'cycles', 'currency', 'optionGroups', 'addons', 'apps'));
    }

    public function index()
    {
        $clientId = $this->optionalClientId();
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
            'app_slug' => 'nullable|string|max:100',
        ]);

        $product = Product::findOrFail($request->product_id);

        // What the customer buys is the hosting - memory, CPU, disk. Picking an
        // app to start with is a convenience, not the product, so an order with
        // no app is a perfectly good order: the account is opened and they
        // install what they like from the Apps tab afterwards.
        //
        // If they did pick one, it has to be an app we actually offer: the form
        // is built from that list, but the request is not the form.
        $appSlug = null;
        if ($this->productLetsCustomerPickApp($product)) {
            $chosen = trim((string) $request->input('app_slug'));
            if ($chosen !== '') {
                $offered = collect($this->sellableApps($product))->pluck('slug')->all();
                if ($offered !== [] && ! in_array($chosen, $offered, true)) {
                    throw ValidationException::withMessages(['app_slug' => __('client.cart.app_not_available')]);
                }
                $appSlug = $chosen;
            }
        }
        $clientId = $this->optionalClientId();
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
            $request->input('addons', []),
            $appSlug
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
            'epp_code' => 'required_if:type,transfer|nullable|string|max:255',
        ]);

        $domain = Domain::normalise($request->domain);
        $type = $request->type;
        $years = (int) ($request->years ?? 1);
        $eppCode = $request->input('epp_code');
        $clientId = $this->optionalClientId();
        $cart = $this->cartService->getOrCreateCart($clientId);

        $updatedCart = $this->cartService->addDomain($cart, $domain, $type, $years, $eppCode);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => __('messages.success.domain_added_to_cart', ['type' => ucfirst($type), 'domain' => $domain])]);
        }

        return redirect()->route('client.cart.index')
            ->with('success', __('messages.success.domain_added_to_cart', ['type' => ucfirst($type), 'domain' => $domain]));
    }

    public function removeItem(Request $request, int $index)
    {
        $clientId = $this->optionalClientId();
        $cart = $this->cartService->getOrCreateCart($clientId);
        $this->cartService->removeItem($cart, $index);

        return redirect()->route('client.cart.index')
            ->with('success', __('messages.success.item_removed_from_cart'));
    }

    public function applyPromo(Request $request)
    {
        $request->validate(['code' => 'required|string|max:50']);

        $clientId = $this->optionalClientId();
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
        $clientId = $this->optionalClientId();
        $client = $this->currentClient();

        // Closing or suspending an account should stop new business; the
        // status was set on the admin screen and read by nothing. A visitor
        // has no account yet - they open one on this very page.
        if ($clientId && (! $client || $client->status !== ClientStatus::Active)) {
            return back()->withErrors(['payment_method' => __('client.cart.account_not_active')]);
        }

        if (! $clientId) {
            // A guest who chooses "sign in" instead of the inline form should
            // land back here, cart intact - not on the dashboard wondering
            // where their order went.
            redirect()->setIntendedUrl(route('client.cart.checkout'));
        }

        $cart = $this->cartService->getOrCreateCart($clientId);
        $totals = $this->cartService->calculateTotal($cart);

        if (empty($totals['items'])) {
            return redirect()->route('client.cart.index')
                ->with('error', __('messages.error.cart_is_empty'));
        }

        $currency = Currency::getDefault();
        $paymentMethods = $this->getAvailablePaymentMethods();
        $needsBillingAddress = $this->needsBillingAddress($client);
        $countries = \App\Support\Countries::all();

        return view('client.cart.checkout', compact('cart', 'totals', 'currency', 'paymentMethods', 'client', 'needsBillingAddress', 'countries'));
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

        $clientId = $this->optionalClientId();
        $billing = [];

        if (! $clientId) {
            // The account is opened here, on the payment step - the one moment
            // the visitor is committed. Same rules as the register page, same
            // service creating the same records; the cart is remembered BEFORE
            // login rotates the session id it is keyed by, or it would
            // evaporate at the exact moment it was being paid for.
            $account = $request->validate([
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:8|confirmed',
            ]);

            if (\App\Models\BannedEmail::blocks($account['email'])) {
                return back()->withErrors(['email' => __('auth.email_not_accepted')])->withInput();
            }

            $billing = $this->validateBillingAddress($request);

            $guestCart = $this->cartService->getOrCreateCart(null);

            [$user, $newClient] = app(\App\Services\ClientRegistrationService::class)
                ->register($account + $billing, $request);

            // The other door into an account, and it needs the same proof:
            // without this the visitor is stopped at the gate below holding a
            // verification page for a mail nobody sent.
            \App\Http\Controllers\Client\EmailVerificationController::send($user);

            $guestCart->update(['user_id' => $newClient->id]);

            auth()->login($user);
            $request->session()->regenerate();
            session()->forget('guest_cart_id');
            session(['active_client_id' => $newClient->id]);

            $clientId = $newClient->id;
            $client = $newClient;
        } else {
            $client = $this->currentClient();

            if ($this->needsBillingAddress($client)) {
                $billing = $this->validateBillingAddress($request);

                if ($client) {
                    $client->fill($billing)->save();
                }
            }
        }

        // Closing or suspending an account should stop new business; the
        // status was set on the admin screen and read by nothing.
        if (! $client || $client->status !== ClientStatus::Active) {
            return back()->withErrors(['payment_method' => __('client.cart.account_not_active')]);
        }

        // An unverified address is where the money stops. Signing in and
        // looking around is harmless; selling a service to an address that
        // was never proved means the invoice, the password reset and the
        // suspension notice all go to somebody who will never see them.
        // The marker sends them back here the moment they verify.
        if ($request->user() && \App\Http\Controllers\Client\EmailVerificationController::required()
            && ! $request->user()->hasVerifiedEmail()) {
            $request->session()->put('checkout_after_verification', true);

            return redirect()->route('client.verification.notice')
                ->with('warning', __('client.email_verify.needed_for_order'));
        }

        $cart = $this->cartService->getOrCreateCart($clientId);
        $totals = $this->cartService->calculateTotal($cart);

        if (empty($totals['items'])) {
            return redirect()->route('client.cart.index')
                ->with('error', __('messages.error.cart_is_empty'));
        }

        $order = $this->cartService->checkout($cart, $clientId, $request->payment_method);

        // A customer who chose to pay by card landed on the invoice and had to
        // press "pay by card" there as well. They already made that choice, so
        // they go straight to the payment screen. If anything goes wrong they
        // land on the invoice and can pay from there - the order is never lost.
        if ($payScreen = $this->cardPaymentScreen($order, (string) $request->payment_method)) {
            return $payScreen;
        }

        return redirect()->route('client.invoices.show', $order->invoice_id)
            ->with('success', __('messages.success.order_placed', ['num' => $order->order_num]));
    }

    /**
     * The redirect that opens a card gateway's own payment screen.
     *
     * Only for gateways whose capture() is known to return a payment page.
     * Calling capture() blindly is not safe: some gateways open a record at
     * the other end the moment they are called, so the ones that belong here
     * are written out rather than guessed. A new one joins with a line.
     *
     * Returns null when no screen can be opened, and the caller falls back to
     * the invoice page.
     */
    private function cardPaymentScreen($order, string $method): ?\Illuminate\Http\RedirectResponse
    {
        if (strtolower(trim($method)) !== 'iyzico' || empty($order->invoice_id)) {
            return null;
        }

        $invoice = \App\Models\Invoice::find($order->invoice_id);
        if (! $invoice || $invoice->amountDue() <= 0) {
            return null;
        }

        $module = app(ModuleRegistry::class)->getGatewayModule('iyzico');
        if (! $module) {
            return null;
        }

        $result = $module->capture($invoice, $invoice->amountDue());

        if (! ($result['success'] ?? false) || empty($result['payment_page_url'])) {
            \Illuminate\Support\Facades\Log::warning('Card screen could not be opened after the order; sent to the invoice instead', [
                'order' => $order->order_num,
                'invoice' => $invoice->id,
                'reason' => $result['message'] ?? 'no payment page address returned',
            ]);

            return null;
        }

        return redirect()->away($result['payment_page_url']);
    }

    /**
     * The gateways the operator has switched on.
     *
     * This used to be a fixed list of three, so a customer could pick a card
     * payment on an installation where no card gateway was configured and be
     * quietly handed a bank transfer at the next step.
     */
    /**
     * Whether this order has to stop and ask for an invoicing address.
     *
     * A guest is opening an account here, so they always answer. An existing
     * customer answers only if the address was never captured - which is
     * every customer who signed up before this screen asked, and every one
     * who came through the inline checkout form.
     */
    /**
     * The address an invoice will be issued to.
     *
     * Checked after the account details so that a guest with a taken or
     * banned email hears about the email first - the thing they have to fix
     * before anything else can matter.
     *
     * @return array<string, mixed>
     */
    private function validateBillingAddress(Request $request): array
    {
        $rules = [
            'address1' => 'required|string|max:255',
            'address2' => 'nullable|string|max:255',
            'city' => 'required|string|max:255',
            'state' => 'nullable|string|max:100',
            'postcode' => 'required|string|max:20',
            'country' => 'required|string|size:2',
            'tax_id' => 'nullable|string|max:50',
        ];

        // A seller bound by the Turkish invoicing rules has to know which kind
        // of buyer this is and identify them accordingly. The same rules the
        // admin screens and the profile page read.
        if (\App\Support\BillingIdentity::turkish()) {
            // array_merge, not +: tax_id is already above as optional and the
            // union operator kept that, so a company could check out without
            // a tax number.
            $rules = array_merge($rules, [
                'phone_number' => 'required|string|max:30',
                'client_type' => 'required|in:individual,company',
                'company_name' => 'required_if:client_type,company|nullable|string|max:255',
                'tax_office' => 'required_if:client_type,company|nullable|string|max:100',
                'tax_id' => 'required_if:client_type,company|nullable|string|max:50',
                'national_id' => 'required_if:client_type,individual|nullable|string|max:20',
            ]);
        }

        return $request->validate($rules);
    }

    private function needsBillingAddress(?Client $client): bool
    {
        if (! $client) {
            return true;
        }

        // An address on file is not enough where the seller needs more: a
        // Turkish seller cannot invoice a company without its tax office.
        return trim((string) $client->address1) === ''
            || trim((string) $client->city) === ''
            || trim((string) $client->postcode) === ''
            || $client->missingBillingIdentity() !== [];
    }

    private function getAvailablePaymentMethods(): array
    {
        $registry = app(ModuleRegistry::class);

        // Switched on is not the same as ready: a gateway missing the keys it
        // authenticates with fails at the last step, after the order is placed.
        $active = collect($registry->usableGateways());

        if ($active->isEmpty()) {
            return ['banktransfer' => __('messages.payment_method.bank_transfer')];
        }

        return $active->mapWithKeys(fn (string $name) => [
            $name => payment_method_label($name),
        ])->all();
    }

    /** Whether this product asks the customer which app to install. */
    private function productLetsCustomerPickApp(Product $product): bool
    {
        $config = is_string($product->config_options)
            ? json_decode($product->config_options, true)
            : ($product->config_options ?? []);

        return ! empty($config['panelica_app_choose']);
    }

    /**
     * The apps this product may be ordered with.
     *
     * The panel says what exists; we say what is on the shelf. Anything that
     * goes wrong answers with an empty list, and the order form then says the
     * catalogue is unavailable rather than offering a choice that cannot be
     * delivered.
     *
     * @return array<int, array<string, mixed>>
     */
    private function sellableApps(Product $product): array
    {
        $server = Server::where('type', $product->server_type ?: 'panelica')->where('active', true)->first();
        if (! $server) {
            return [];
        }
        try {
            $module = app(ModuleRegistry::class)->getServerModule($server->type);
            if (! $module || ! method_exists($module, 'appTemplates')) {
                return [];
            }

            return DockerApp::decorate($module->appTemplates($server), sellableOnly: true);
        } catch (\Throwable $e) {
            return [];
        }
    }
}
