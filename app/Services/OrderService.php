<?php

namespace App\Services;

use App\Enums\DomainStatus;
use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\ServiceStatus;
use App\Events\OrderPlaced;
use App\Models\Client;
use App\Models\Domain;
use App\Models\DomainPricing;
use App\Models\Order;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Service;
use App\Models\ServiceAddon;
use App\Models\SslOrder;
use App\Services\Module\ModuleRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class OrderService
{
    public function __construct(
        protected InvoiceService $invoiceService,
        protected InvoiceGenerationService $invoiceGenerationService,
        protected ProvisioningService $provisioning
    ) {}

    /**
     * Process a new order:
     * 1. Create the Order record
     * 2. Create Service / Domain records for each item
     * 3. Generate an invoice
     * 4. Optionally apply a promotion code
     *
     * @param  array  $items  Each item: ['type' => 'service'|'domain', 'product_id' => ..., 'domain' => ..., 'billing_cycle' => ..., 'amount' => ...]
     * @return Order The created order with invoice attached.
     */
    public function processOrder(Client $client, array $items, string $paymentMethod, ?string $promoCode = null): Order
    {
        $order = DB::transaction(function () use ($client, $items, $paymentMethod, $promoCode) {
            $orderNum = $this->generateOrderNumber();
            $totalAmount = array_sum(array_column($items, 'amount'));

            $order = Order::create([
                'order_num' => $orderNum,
                'client_id' => $client->id,
                'date' => now()->toDateString(),
                'amount' => $totalAmount,
                'payment_method' => $paymentMethod,
                'status' => OrderStatus::Pending->value,
                'ip_address' => request()->ip() ?? '0.0.0.0',
                'promo_code' => $promoCode,
            ]);

            $invoiceItems = [];

            foreach ($items as $item) {
                $type = strtolower($item['type'] ?? 'service');

                if ($type === 'domain') {
                    $domain = $this->createDomainForOrder($order, $client, $item);
                    $years = max(1, (int) ($item['registration_period'] ?? 1));
                    $action = strtolower((string) ($item['domain_type'] ?? 'register')) === 'transfer'
                        ? 'Transfer'
                        : 'Registration';

                    $invoiceItems[] = [
                        'type' => 'Domain',
                        // Points at the domain: paying this line is what renews
                        // it, and the renewal generator dedupes on it.
                        'rel_id' => $domain->id,
                        'description' => "Domain {$action}: ".($item['domain'] ?? '')." — {$years} Year(s)",
                        'amount' => (float) ($item['amount'] ?? 0),
                        'taxed' => true,
                    ];
                } else {
                    $product = Product::find($item['product_id'] ?? null);

                    // A hidden plan is a draft nobody meant to sell and a
                    // retired one is a plan the operator has stopped selling.
                    // The cart refuses both; this is the other door, and the
                    // order endpoint hands it product ids straight from the
                    // request.
                    if ($product && ($product->hidden || $product->retired)) {
                        throw ValidationException::withMessages([
                            'product_id' => __('client.cart.product_unavailable'),
                        ]);
                    }

                    // Guarded at the database, not in PHP: two customers can be
                    // buying the last one at the same moment.
                    if ($product && $product->stock_control) {
                        $taken = Product::whereKey($product->id)
                            ->where('stock_qty', '>', 0)
                            ->decrement('stock_qty');

                        if ($taken === 0) {
                            throw ValidationException::withMessages([
                                'product_id' => __('client.cart.out_of_stock'),
                            ]);
                        }
                    }

                    $service = $this->createServiceForOrder($order, $client, $item);

                    // Configurable options are already inside the service price;
                    // recording them is what lets the panel and the server
                    // module see what was ordered.
                    if (! empty($item['config_options'])) {
                        app(ConfigOptionService::class)->attachToService($service, $item['config_options']);
                    }

                    // Addons are billed separately and renew on their own dates.
                    if (! empty($item['addons'])) {
                        $addonService = app(AddonService::class);
                        foreach ($addonService->attachToService($service, $item['addons'], $order) as $serviceAddon) {
                            $line = $addonService->lineItem($serviceAddon);
                            $invoiceItems[] = [
                                'type' => $line['type'],
                                'rel_id' => $line['rel_id'],
                                'description' => $line['description'],
                                'amount' => $line['amount'],
                                'taxed' => $line['taxed'],
                            ];
                        }
                    }

                    $invoiceItems[] = [
                        'type' => 'Hosting',
                        'rel_id' => $service->id,
                        'description' => $this->buildServiceDescription($service, $item),
                        'amount' => (float) ($item['amount'] ?? $service->amount),
                        // r146-taxflag: ask the product, as everything else
                        // does. This said true whatever the product carried, so
                        // one marked not taxable was taxed on the invoice the
                        // customer pays at sign-up and never again on a
                        // renewal - the generator, the upgrade charge and the
                        // addon line have always read the product's own flag.
                        'taxed' => (bool) ($product->tax ?? true),
                    ];
                }
            }

            // Generate invoice for the order
            $invoice = $this->invoiceService->createInvoice($client, $invoiceItems, [
                'payment_method' => $paymentMethod,
                'notes' => "Order #{$orderNum}",
            ]);

            // Apply promotion if provided
            if ($promoCode) {
                $this->invoiceGenerationService->applyPromotion($invoice, $promoCode);
                $invoice = $invoice->fresh();
            }

            // Link invoice to order
            $order->update(['invoice_id' => $invoice->id]);

            return $order->fresh();
        });

        event(new OrderPlaced($order));

        // Screen the order the customer just placed. This only ever ran from
        // an API endpoint before, so a banned email or IP ordering through the
        // shop was never looked at.
        try {
            $fraud = app(FraudDetectionService::class)->evaluate($order);

            if (($fraud['score'] ?? 0) >= 60) {
                Log::warning('Order #'.$order->order_num.' held as fraud', ['reasons' => $fraud['reasons'] ?? []]);
                $order = $this->markFraud($order);

                return $order->fresh();
            }
        } catch (\Throwable $e) {
            // Screening must never stop a legitimate customer from ordering.
            Log::error('Fraud screening failed for order #'.$order->id.': '.$e->getMessage());
        }

        // Products configured with auto_setup = 'order' are provisioned the
        // moment the order is placed, without waiting for payment.
        $this->provisionOnOrderPlacement($order);

        // Zero-total invoices (free products, 100% promotions) settle
        // immediately, which drives the normal payment→provisioning chain.
        $invoice = $order->invoice()->first();
        if ($invoice && (float) $invoice->total <= 0.009) {
            $this->invoiceService->markPaid($invoice, null, 'system');
        }

        return $order->fresh();
    }

    /**
     * Provision services whose product opts into setup-on-order-placement.
     * Runs outside the order transaction — module calls hit external APIs.
     */
    private function provisionOnOrderPlacement(Order $order): void
    {
        $services = Service::where('order_id', $order->id)
            ->where('status', ServiceStatus::Pending->value)
            ->with('product')
            ->get();

        foreach ($services as $svc) {
            $autoSetup = strtolower((string) ($svc->product?->auto_setup ?? ''));
            if ($autoSetup === 'order' && $svc->product?->server_type) {
                $result = $this->provisioning->createAccount($svc);
                Log::info('Setup-on-order provisioning for service #'.$svc->id, [
                    'success' => $result['success'] ?? false,
                ]);
            }
        }
    }

    /**
     * Accept a pending order and provision its services.
     *
     * $manual = true  → admin explicitly accepted: everything is provisioned,
     *                   including products with auto_setup = 'manual'.
     * $manual = false → automatic acceptance (payment received / order placed):
     *                   auto_setup = 'manual' services stay pending and the
     *                   order remains pending for admin review.
     *
     * Services are activated only AFTER their module create succeeds; failures
     * stay pending and land in the module queue for automatic retry.
     */
    public function acceptOrder(Order $order, bool $manual = false): Order
    {
        if ($order->status === OrderStatus::Active->value) {
            return $order;
        }

        // A held order stays held. Paying for it is not a reason to provision
        // it; an operator has to look at it first.
        if (in_array($order->status, [OrderStatus::Fraud->value, OrderStatus::Cancelled->value], true)) {
            Log::info('Order #'.$order->order_num.' not accepted: status is '.$order->status);

            return $order;
        }

        $pendingServices = Service::where('order_id', $order->id)
            ->where('status', ServiceStatus::Pending->value)
            ->with('product')
            ->get();

        $awaitingManual = false;

        foreach ($pendingServices as $svc) {
            $autoSetup = strtolower((string) ($svc->product?->auto_setup ?? 'payment'));

            if (! $manual && $autoSetup === 'manual') {
                $awaitingManual = true;

                continue;
            }

            if ($svc->product && $svc->product->server_type) {
                // createAccount activates the service and fires ServiceActivated
                // on success; on failure it queues a retry and the service
                // stays pending (no "active but never provisioned" state).
                $result = $this->provisioning->createAccount($svc);
                if ($result['success'] ?? false) {
                    Log::info('Auto-provisioned service #'.$svc->id.' on order accept');
                } else {
                    Log::error('Auto-provision failed for service #'.$svc->id.': '.($result['message'] ?? 'unknown'));
                }
            } else {
                // No server module involved — plain activation. Legitimate for
                // a product that is not hosted anywhere; for one that is, this
                // is the whole order quietly doing nothing, so say so.
                if (in_array((string) $svc->product?->type, ['hosting', 'reseller', 'vps'], true)) {
                    Log::warning('Service activated with no server module: nothing was created on any server', [
                        'service_id' => $svc->id,
                        'product_id' => $svc->product?->id,
                        'product' => $svc->product?->name,
                        'product_type' => $svc->product?->type,
                        'hint' => 'Set the product\'s server module.',
                    ]);
                }

                $svc->update([
                    'status' => ServiceStatus::Active->value,
                    'registration_date' => $svc->registration_date ?? now()->toDateString(),
                ]);
            }
        }

        // r133-register: a domain is not active because we said so.
        //
        // This used to flip every pending domain to active in one update and
        // tell no registrar, while register() sat implemented in all four
        // registrar modules. A customer bought a domain, paid for it, and the
        // panel showed it live while no registry had heard of it.
        foreach (Domain::where('order_id', $order->id)->where('status', DomainStatus::Pending->value)->get() as $domain) {
            $this->registerOrderedDomain($order, $domain);
        }

        // A certificate cannot be issued until the customer supplies a CSR, so
        // paying for one has to open the order and ask them for it.
        foreach ($pendingServices as $svc) {
            if (strtolower((string) ($svc->product?->type ?? '')) !== 'ssl') {
                continue;
            }

            $sslOrder = SslOrder::firstOrCreate(
                ['service_id' => $svc->id],
                [
                    'client_id' => $svc->client_id,
                    'module' => $svc->product?->ssl_module,
                    'domain' => $svc->domain,
                    'status' => 'Awaiting Configuration',
                ]
            );

            if ($sslOrder->wasRecentlyCreated) {
                app(SslProvisioningService::class)->sendConfigurationRequiredEmail($sslOrder);
            }
        }

        // Addons ordered alongside a service start billing from the service's
        // own renewal date, which is what the customer was quoted.
        foreach (ServiceAddon::where('order_id', $order->id)->where('status', 'pending')->get() as $serviceAddon) {
            $serviceAddon->update([
                'status' => 'active',
                'next_due_date' => $serviceAddon->next_due_date
                    ?? $serviceAddon->service?->next_due_date
                    ?? BillingCycleHelper::advance(now(), $serviceAddon->billing_cycle ?: 'Monthly')->toDateString(),
            ]);
        }

        if ($awaitingManual) {
            // Keep the order pending so it shows up for admin review.
            run_hook('PendingOrder', ['order' => $order]);
            app(NotificationService::class)->dispatch('order.awaiting_acceptance', [
                'event_type' => 'order.awaiting_acceptance',
                'subject' => 'Order awaiting manual acceptance',
                'message' => "Order #{$order->order_num} is paid but contains products that require manual acceptance.",
                'order_id' => $order->id,
                'order_num' => $order->order_num,
            ]);
        } else {
            $order->update(['status' => OrderStatus::Active->value]);
            run_hook('AcceptOrder', ['order' => $order->fresh(), 'manual' => $manual]);
        }

        return $order->fresh();
    }

    /**
     * Cancel an order and terminate all related services.
     */
    public function cancelOrder(Order $order): Order
    {
        if (in_array($order->status, [OrderStatus::Cancelled->value, OrderStatus::Fraud->value])) {
            return $order;
        }

        run_hook('CancelOrder', ['order' => $order]);

        return DB::transaction(function () use ($order) {
            $order->update(['status' => OrderStatus::Cancelled->value]);

            Service::where('order_id', $order->id)
                ->whereNotIn('status', [ServiceStatus::Terminated->value, ServiceStatus::Cancelled->value])
                ->update([
                    'status' => ServiceStatus::Cancelled->value,
                    'termination_date' => now()->toDateString(),
                ]);

            Domain::where('order_id', $order->id)
                ->whereNotIn('status', [DomainStatus::Expired->value, DomainStatus::Cancelled->value])
                ->update(['status' => DomainStatus::Cancelled->value]);

            // Cancel the linked invoice if still unpaid
            if ($order->invoice_id) {
                $invoice = $order->invoice;
                if ($invoice && in_array($invoice->status, [InvoiceStatus::Unpaid->value, InvoiceStatus::Overdue->value])) {
                    $this->invoiceService->cancelInvoice($invoice);
                }
            }

            return $order->fresh();
        });
    }

    /** Written onto every service an order's fraud verdict suspends. */
    private const FRAUD_SUSPENSION_REASON = 'Order marked as fraud';

    /**
     * Mark an order as fraud and suspend all related services.
     */
    public function markFraud(Order $order): Order
    {
        run_hook('FraudOrder', ['order' => $order]);

        $services = DB::transaction(function () use ($order) {
            $order->update([
                'status' => OrderStatus::Fraud->value,
                'fraud_module' => 'manual',
                'fraud_output' => 'Manually marked as fraud by admin on '.now()->toDateTimeString(),
            ]);

            $services = Service::where('order_id', $order->id)
                ->where('status', ServiceStatus::Active->value)
                ->get();

            foreach ($services as $service) {
                // A service on a server is suspended below, on the server, and
                // ProvisioningService writes the status once that succeeds.
                if ($service->server_id) {
                    continue;
                }

                $service->update([
                    'status' => ServiceStatus::Suspended->value,
                    'suspension_date' => now()->toDateString(),
                    'suspension_reason' => self::FRAUD_SUSPENSION_REASON,
                ]);
            }

            // Cancel unpaid invoice
            if ($order->invoice_id) {
                $invoice = $order->invoice;
                if ($invoice && in_array($invoice->status, [InvoiceStatus::Unpaid->value, InvoiceStatus::Overdue->value])) {
                    $this->invoiceService->cancelInvoice($invoice);
                }
            }

            return $services;
        });

        // The whole point of calling an order fraudulent is that the account
        // stops serving. This used to be a query-builder update: no server was
        // ever told, so the site the fraudster ordered carried on running.
        // Kept outside the transaction so an unreachable panel cannot hold it
        // open; a refusal is queued for retry by ProvisioningService.
        foreach ($services as $service) {
            if ($service->server_id) {
                $this->provisioning->suspendAccount($service, self::FRAUD_SUSPENSION_REASON);
            }
        }

        return $order->fresh();
    }

    /**
     * Soft-delete (cancel) the order and all related items.
     */
    public function deleteOrder(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $this->cancelOrder($order);
            $order->delete();
        });
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function createServiceForOrder(Order $order, Client $client, array $item): Service
    {
        $billingCycle = $item['billing_cycle'] ?? 'Monthly';

        return Service::create([
            'client_id' => $client->id,
            'order_id' => $order->id,
            'product_id' => $item['product_id'] ?? null,
            'server_id' => $item['server_id'] ?? null,
            // The same reading the shop gives it: the cart normalises what
            // was typed, orders are built here, and the order endpoint hands
            // this method the request verbatim - so a pasted URL used to be
            // written onto the service and passed to the panel as the name.
            'domain' => Domain::normalise($item['domain'] ?? null) ?: null,
            'payment_method' => $order->payment_method,
            'qty' => $item['qty'] ?? 1,
            'first_payment_amount' => $item['first_payment_amount'] ?? $item['amount'] ?? 0,
            'amount' => $item['amount'] ?? 0,
            'billing_cycle' => $billingCycle,
            'next_due_date' => $this->calculateNextDueDate($billingCycle),
            'registration_date' => now()->toDateString(),
            'status' => ServiceStatus::Pending->value,
            'username' => $item['username'] ?? null,
            'notes' => $item['notes'] ?? null,
        ]);
    }

    /**
     * Put a paid domain in front of its registrar.
     *
     * The module writes the registration date, expiry and status itself when
     * it succeeds. A refusal leaves the domain pending and raises it, because
     * only a person can sort out a registry that said no.
     *
     * A transfer cannot be started from here - it needs an EPP code, which the
     * order does not collect - so those are recorded as before and flagged for
     * somebody to start by hand.
     */
    private function registerOrderedDomain(Order $order, Domain $domain): void
    {
        $registrar = app(ModuleRegistry::class)
            ->getRegistrarModule((string) $domain->registrar);

        $isTransfer = strtolower((string) $domain->type) === 'transfer';

        if (! $registrar || $isTransfer) {
            if ($isTransfer && $registrar) {
                Log::info("Domain #{$domain->id} ({$domain->domain}) is a transfer: it needs an EPP code and has to be started by hand.");
            }

            $domain->update(['status' => DomainStatus::Active->value]);

            return;
        }

        $client = $order->client;

        $params = array_filter([
            'firstname' => $client?->first_name,
            'lastname' => $client?->last_name,
            'email' => $client?->email,
            'phone' => $client?->phone_number,
            'address' => $client?->address1,
            'city' => $client?->city,
            'state' => $client?->state,
            'postcode' => $client?->postcode,
            'country' => $client?->country,
        ]);

        try {
            $result = $registrar->register($domain, max(1, (int) $domain->registration_period), $params);
        } catch (\Throwable $e) {
            Log::error("Domain registration threw for {$domain->domain}: ".$e->getMessage());
            $result = ['success' => false, 'message' => $e->getMessage()];
        }

        if ($result['success'] ?? false) {
            $domain->refresh();

            if (strtolower((string) $domain->status) !== DomainStatus::Active->value) {
                $domain->update(['status' => DomainStatus::Active->value]);
            }

            return;
        }

        $reason = (string) ($result['message'] ?? 'no reason given');

        Log::error("Domain registration failed for {$domain->domain}: {$reason}");

        try {
            app(NotificationService::class)->dispatch('domain.registration_failed', [
                'event_type' => 'domain.registration_failed',
                'subject' => 'Domain registration failed',
                'message' => "The registrar would not register {$domain->domain}: {$reason}. "
                    .'The customer has paid for it; the domain is not registered and needs attention.',
                'domain_id' => $domain->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Domain registration alert failed: '.$e->getMessage());
        }
    }

    /**
     * The registrar the operator set up for this domain's extension.
     *
     * Read from the TLD pricing table, where the field lives.
     */
    private function registrarForTld(string $domain): ?string
    {
        $domain = Domain::normalise($domain);

        if ($domain === '' || ! str_contains($domain, '.')) {
            return null;
        }

        $tld = '.'.substr($domain, strpos($domain, '.') + 1);

        $registrar = DomainPricing::whereRaw('LOWER(extension) = ?', [strtolower($tld)])
            ->value('auto_registrar');

        return filled($registrar) ? (string) $registrar : null;
    }

    private function createDomainForOrder(Order $order, Client $client, array $item): Domain
    {
        return Domain::create([
            'client_id' => $client->id,
            'order_id' => $order->id,
            'domain' => Domain::normalise($item['domain'] ?? ''),
            'type' => $item['domain_type'] ?? 'register',
            // r148-autoregistrar: the TLD pricing screen has a registrar field
            // for exactly this, and nothing read it - every domain ordered
            // through the shop was created as Manual, so a TLD set up to
            // register through eNom was marked active without any registry
            // hearing about it. An order that names its own registrar still
            // wins; a TLD with none set is still done by hand.
            'registrar' => $item['registrar'] ?? $this->registrarForTld($item['domain'] ?? '') ?? 'Manual',
            'registration_date' => now()->toDateString(),
            'expiry_date' => now()->addYears(max(1, (int) ($item['registration_period'] ?? 1)))->toDateString(),
            'next_due_date' => now()->addYears(max(1, (int) ($item['registration_period'] ?? 1)))->toDateString(),
            'status' => DomainStatus::Pending->value,
            'registration_period' => (int) ($item['registration_period'] ?? 1),
            // What the renewal costs, which is not what was paid to register.
            'recurring_amount' => $item['renewal_amount'] ?? $item['amount'] ?? 0,
            'first_payment_amount' => $item['amount'] ?? 0,
            'payment_method' => $order->payment_method,
        ]);
    }

    private function calculateNextDueDate(string $billingCycle): string
    {
        return match (strtolower($billingCycle)) {
            'monthly' => now()->addMonth()->toDateString(),
            'quarterly' => now()->addMonths(3)->toDateString(),
            'semi-annually' => now()->addMonths(6)->toDateString(),
            'annually' => now()->addYear()->toDateString(),
            'biennially' => now()->addYears(2)->toDateString(),
            'triennially' => now()->addYears(3)->toDateString(),
            default => now()->addMonth()->toDateString(),
        };
    }

    private function buildServiceDescription(Service $service, array $item): string
    {
        $product = $service->product;
        $name = $product?->name ?? ($item['description'] ?? 'Hosting Service');
        $cycle = $service->billing_cycle ?? 'Monthly';
        $domain = $service->domain ? " — {$service->domain}" : '';

        $configured = ! empty($item['config_options'])
            ? app(ConfigOptionService::class)->summarise($item['config_options'])
            : '';

        // The parenthesis shows the paid billing period, not the raw cycle
        // name, matching the renewal invoices (2026/08/15 - 2026/09/15).
        $dueDate = $service->next_due_date
            ? Carbon::parse($service->next_due_date)
            : Carbon::now()->addMonths(Service::monthsInCycle($cycle));
        $periodStart = $dueDate->copy()->subMonths(Service::monthsInCycle($cycle));
        $period = $periodStart->format('Y/m/d').' - '.$dueDate->format('Y/m/d');

        return "{$name} ({$period}){$domain}".($configured ? " ({$configured})" : '');
    }

    private function generateOrderNumber(): string
    {
        return strtoupper(Str::random(8));
    }
}
