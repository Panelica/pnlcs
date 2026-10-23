<?php

namespace App\Http\Controllers\Client;

use App\Enums\ClientStatus;
use App\Http\Controllers\Concerns\ResolvesClient;
use App\Http\Controllers\Controller;
use App\Models\Cart;
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
use App\Support\FreeSubdomains;
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

        $productFields = \App\Models\CustomField::productFields($product->id)->where('admin_only', false)->get();

        return view('client.cart.configure', compact('product', 'cycles', 'currency', 'optionGroups', 'addons', 'apps', 'productFields'));
    }

    public function index()
    {
        $clientId = $this->optionalClientId();
        $cart = $this->cartService->getOrCreateCart($clientId);
        $this->cartService->applyPendingPromo($cart);
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
            'domain_option' => 'nullable|string|in:register,transfer,own,subdomain',
            'subdomain_parent' => 'nullable|string|max:253',
            'notes' => 'nullable|string|max:2000',
            'config_options' => 'nullable|array',
            'addons' => 'nullable|array',
            'addons.*' => 'integer',
            'app_slug' => 'nullable|string|max:100',
            'epp_code' => 'nullable|string|max:255',
            'ssh_keys' => 'nullable|string|max:8000',
        ]);

        $product = Product::findOrFail($request->product_id);

        // The product's own questions. Admin-only fields are staff's, not the
        // customer's to answer.
        $productFields = \App\Models\CustomField::productFields($product->id)->where('admin_only', false)->get();
        [$fieldRules, $fieldNames] = \App\Models\CustomField::rulesFor($productFields);
        $customFields = $fieldRules === [] ? [] : (array) ($request->validate($fieldRules, [], $fieldNames)['custom_fields'] ?? []);

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
        // SSH keys for a virtual server, put on it when it is built.
        $sshKeys = null;
        if (strtolower((string) $product->server_type) === 'proxmox' && trim((string) $request->input('ssh_keys')) !== '') {
            $sshKeys = \Modules\Servers\Proxmox\ProxmoxModule::normaliseSshKeys($request->input('ssh_keys'));
            if ($sshKeys === null) {
                throw ValidationException::withMessages(['ssh_keys' => __('proxmox.client.ssh_keys_invalid')]);
            }
        }

        // The same reading the search box gives it: a customer pastes an
        // address, and it used to be stored exactly as pasted.
        $domain = Domain::normalise($request->domain) ?: null;

        // A free subdomain: the customer gives the first label and picks one of
        // the shop's domains. A name under those domains typed as the
        // customer's own goes through the same checks, so www.<shop domain>
        // cannot be ordered either way.
        if ($request->input('domain_option') === 'subdomain' && $domain !== null) {
            $parent = ltrim(strtolower(trim((string) $request->input('subdomain_parent'))), '.');
            $domain = str_ends_with($domain, '.'.$parent) ? $domain : $domain.'.'.$parent;
        }
        if ($domain !== null && ($request->input('domain_option') === 'subdomain' || FreeSubdomains::parentOf($domain) !== null)) {
            if ($problem = FreeSubdomains::problem($domain)) {
                throw ValidationException::withMessages(['domain' => $problem]);
            }
        }

        // A domain already set up on the servers this order would go to: setup
        // would refuse it and leave the service in Pending, so the customer
        // hears it now (#212).
        if ($domain !== null && ($problem = \App\Services\HostedDomainCheck::problem($product, $domain))) {
            throw ValidationException::withMessages(['domain' => $problem]);
        }

        // "Register a new domain" and "Transfer" are purchases of their own.
        // They used to be a note on the service - never checked, priced,
        // invoiced or registered (issue #48). The name is checked here, before
        // anything goes into the cart, so a refusal leaves no hosting behind
        // without the domain it was ordered for.
        $domainOption = $request->input('domain_option');
        $buysDomain = $product->show_domain_options && $domain !== null
            && in_array($domainOption, ['register', 'transfer'], true);
        if ($buysDomain) {
            if ($domainOption === 'transfer' && trim((string) $request->input('epp_code')) === '') {
                throw ValidationException::withMessages(['epp_code' => __('client.cart.epp_code_required')]);
            }
            $quote = $this->cartService->quoteDomain($domain, $domainOption);
            if ($problem = $this->cartService->domainQuoteProblem($quote)) {
                throw ValidationException::withMessages(['domain' => $problem]);
            }
        }

        $clientId = $this->optionalClientId();
        $cart = $this->cartService->getOrCreateCart($clientId);

        $this->cartService->addProduct(
            $cart,
            $product,
            $request->billing_cycle,
            $domain,
            $request->input('config_options', []),
            $request->input('notes'),
            $request->input('domain_option'),
            $request->input('addons', []),
            $appSlug,
            $sshKeys,
            $customFields
        );

        // Already there when the customer found it on the search page first.
        if ($buysDomain && ! $this->cartService->hasDomain($cart, $domain)) {
            $this->cartService->addDomain($cart, $domain, $domainOption, 1, $request->input('epp_code'));
        }

        // Hosting that comes with a free domain: its first year costs nothing
        // when the extension and the billing cycle qualify.
        if ($buysDomain && $product->givesFreeDomain('.'.implode('.', array_slice(explode('.', $domain), 1)), $request->billing_cycle)) {
            $this->cartService->makeDomainFree($cart->fresh(), $domain, $product->id);
        }

        return redirect()->route('client.cart.index')
            ->with('success', __('messages.success.product_added_to_cart'));
    }

    /**
     * Price and availability of the domain typed on the configure page, for
     * its order summary. The cart asks the same question again on submit.
     */
    public function domainQuote(Request $request)
    {
        $request->validate([
            'domain' => 'required|string|max:253',
            'type' => 'required|string|in:register,transfer',
        ]);

        $quote = $this->cartService->quoteDomain(Domain::normalise($request->domain), $request->type);

        return response()->json([
            'domain' => $quote['domain'],
            'type' => $quote['type'],
            'status' => $quote['status'],
            // In the currency the page shows prices in: the order summary adds
            // it to totals that are (CustomerCurrency). The cart prices the
            // domain again, in the shop currency, when the form is sent.
            'price' => display_price($quote['price']),
            'price_formatted' => display_money_fmt($quote['price']),
            'message' => $this->cartService->domainQuoteProblem($quote)
                ?? __($quote['type'] === 'transfer' ? 'client.cart.domain_transfer_quote' : 'client.cart.domain_available',
                    ['domain' => $quote['domain'], 'price' => display_money_fmt($quote['price'])]),
        ]);
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
        $result = $this->cartService->removeItem($cart, $index);

        if (! $result['removed'] && $result['message']) {
            return redirect()->route('client.cart.index')->with('error', $result['message']);
        }

        return redirect()->route('client.cart.index')
            ->with('success', $result['message'] ?? __('messages.success.item_removed_from_cart'));
    }

    /** Add or drop WHOIS privacy on a domain line in the cart. */
    public function domainPrivacy(Request $request, int $index)
    {
        $on = $request->boolean('privacy');
        $cart = $this->cartService->getOrCreateCart($this->optionalClientId());

        if (! $this->cartService->setDomainPrivacy($cart, $index, $on)) {
            return redirect()->route('client.cart.index')->with('error', __('client.cart.privacy_unavailable'));
        }

        return redirect()->route('client.cart.index');
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
        $this->cartService->applyPendingPromo($cart);
        $totals = $this->cartService->calculateTotal($cart);

        if (empty($totals['items'])) {
            return redirect()->route('client.cart.index')
                ->with('error', __('messages.error.cart_is_empty'));
        }

        $currency = Currency::getDefault();
        $paymentMethods = $this->getAvailablePaymentMethods();
        // Nothing in the cart will ever be billed: no payment method to choose.
        $freeOrder = $this->isFreeOrder($totals);
        $needsPhone = $this->needsPhone($cart);
        $needsBillingAddress = $this->needsBillingAddress($client, $needsPhone);
        $countries = \App\Support\Countries::all();
        // The account is opened on this page, so this is where the fields
        // flagged "show on order form" are asked. The flag was saved on the
        // admin screen and read by nothing.
        $orderFields = $clientId ? collect() : \App\Models\CustomField::orderFields()->get();

        return view('client.cart.checkout', compact('cart', 'totals', 'currency', 'paymentMethods', 'freeOrder', 'client', 'needsBillingAddress', 'needsPhone', 'countries', 'orderFields'));
    }

    /**
     * A cart of free products only, at no charge: nothing in it is billed now
     * or later (a free service is never invoiced again), so the customer is
     * not asked how they will pay. A paid product at zero today (a promotion)
     * still asks: its renewals are charged.
     */
    private function isFreeOrder(array $totals): bool
    {
        $items = collect($totals['items'] ?? []);
        if ($items->isEmpty() || (float) ($totals['total'] ?? 0) > 0.009) {
            return false;
        }
        if ($items->contains(fn ($item) => ($item['type'] ?? 'product') !== 'product')) {
            return false;
        }
        $ids = $items->pluck('product_id')->map(fn ($id) => (int) $id)->unique();

        return Product::whereIn('id', $ids)->where('pay_type', 'free')->count() === $ids->count();
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

        // ShoppingCartValidateCheckout, as WHMCS has it: an addon may refuse
        // the order with a message (a string, or a list of them).
        $refusals = collect(run_hook('ShoppingCartValidateCheckout', [
            'cart' => $this->cartService->getOrCreateCart($clientId), 'clientId' => $clientId, 'paymentMethod' => $request->input('payment_method'),
        ]))->flatten()->filter(fn ($m) => is_string($m) && trim($m) !== '')->values();
        if ($refusals->isNotEmpty()) {
            return back()->withErrors(['checkout' => $refusals->all()])->withInput($request->except(['password', 'password_confirmation']));
        }
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

            // The same door as the register page, behind the same switch.
            if (! app(\App\Services\RecaptchaService::class)->passes('signup', $request->input(\App\Services\RecaptchaService::FIELD), $request->ip())) {
                return back()
                    ->withErrors([\App\Services\RecaptchaService::FIELD => __('client.contact.recaptcha_failed')])
                    ->withInput($request->except(['password', 'password_confirmation']));
            }

            if (\App\Models\BannedEmail::blocks($account['email'])) {
                return back()->withErrors(['email' => __('auth.email_not_accepted')])->withInput();
            }

            $billing = $this->validateBillingAddress($request, $this->needsPhone($this->cartService->getOrCreateCart(null)));

            $orderFields = \App\Models\CustomField::orderFields()->get();
            [$fieldRules, $fieldNames] = \App\Models\CustomField::rulesFor($orderFields);
            $request->validate($fieldRules, [], $fieldNames);

            $guestCart = $this->cartService->getOrCreateCart(null);

            [$user, $newClient] = app(\App\Services\ClientRegistrationService::class)
                ->register($account + $billing, $request);

            \App\Models\CustomField::storeValues($newClient->id, $orderFields, (array) $request->input('custom_fields', []));

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
            $needsPhone = $this->needsPhone($this->cartService->getOrCreateCart($clientId));

            if ($this->needsBillingAddress($client, $needsPhone)) {
                $billing = $this->validateBillingAddress($request, $needsPhone);

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

        // Nothing to pay: the zero-total invoice was settled when the order was
        // placed. "Please complete payment" on a paid invoice sent the customer
        // looking for a payment that was never due; they go to their services.
        // Only a zero total: a test account's order is settled without money
        // too (AutoApproveOrderEmails), and that one did cost something.
        $invoice = $order->invoice_id ? \App\Models\Invoice::find($order->invoice_id) : null;
        if ($invoice && strtolower((string) $invoice->status) === 'paid'
            && (float) $invoice->total <= 0.009 && (float) $invoice->credit <= 0.009) {
            $awaitingReview = strtolower((string) $order->fresh()?->status) === 'pending'
                && \App\Models\Service::where('order_id', $order->id)->where('status', 'pending')
                    ->whereHas('product', fn ($q) => $q->where('auto_setup', 'manual'))->exists();

            return redirect()->route('client.services.index')
                ->with('success', __($awaitingReview ? 'messages.success.order_placed_free_review' : 'messages.success.order_placed_free', ['num' => $order->order_num]));
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
    private function validateBillingAddress(Request $request, bool $needsPhone = false): array
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

        // A registry will not take a domain without the registrant's phone:
        // the order was paid and the registration then refused (HTTP 400 from
        // DomainNameAPI), leaving the domain pending with nobody told why.
        if ($needsPhone) {
            $rules['phone_number'] = 'required|string|max:30';
        }

        return $request->validate($rules);
    }

    /**
     * Whether checkout must ask for a phone number: the Turkish invoicing
     * rules ask it of every buyer, and a registry asks it of every registrant.
     */
    private function needsPhone(Cart $cart): bool
    {
        return \App\Support\BillingIdentity::turkish() || $this->cartService->hasDomainOrder($cart);
    }

    private function needsBillingAddress(?Client $client, bool $needsPhone = false): bool
    {
        if (! $client) {
            return true;
        }

        if ($needsPhone && trim((string) $client->phone_number) === '') {
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
