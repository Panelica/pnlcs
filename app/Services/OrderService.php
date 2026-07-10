<?php

namespace App\Services;

use App\Enums\DomainStatus;
use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\ServiceStatus;
use App\Models\Client;
use App\Models\Domain;
use App\Models\Order;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Service;
use App\Models\SslOrder;
use App\Services\SslProvisioningService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Events\OrderPlaced;

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
            $orderNum  = $this->generateOrderNumber();
            $totalAmount = array_sum(array_column($items, 'amount'));

            $order = Order::create([
                'order_num'      => $orderNum,
                'client_id'      => $client->id,
                'date'           => now()->toDateString(),
                'amount'         => $totalAmount,
                'payment_method' => $paymentMethod,
                'status'         => OrderStatus::Pending->value,
                'ip_address'     => request()->ip() ?? '0.0.0.0',
                'promo_code'     => $promoCode,
            ]);

            $invoiceItems = [];

            foreach ($items as $item) {
                $type = strtolower($item['type'] ?? 'service');

                if ($type === 'domain') {
                    $this->createDomainForOrder($order, $client, $item);

                    $invoiceItems[] = [
                        'type'        => 'Domain',
                        'rel_id'      => 0,
                        'description' => 'Domain Registration: ' . ($item['domain'] ?? ''),
                        'amount'      => (float) ($item['amount'] ?? 0),
                        'taxed'       => false,
                    ];
                } else {
                    $service = $this->createServiceForOrder($order, $client, $item);

                    $invoiceItems[] = [
                        'type'        => 'Hosting',
                        'rel_id'      => $service->id,
                        'description' => $this->buildServiceDescription($service, $item),
                        'amount'      => (float) ($item['amount'] ?? $service->amount),
                        'taxed'       => true,
                    ];
                }
            }

            // Generate invoice for the order
            $invoice = $this->invoiceService->createInvoice($client, $invoiceItems, [
                'payment_method' => $paymentMethod,
                'notes'          => "Order #{$orderNum}",
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
                Log::info('Setup-on-order provisioning for service #' . $svc->id, [
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

        $pendingServices = Service::where('order_id', $order->id)
            ->where('status', ServiceStatus::Pending->value)
            ->with('product')
            ->get();

        $awaitingManual = false;

        foreach ($pendingServices as $svc) {
            $autoSetup = strtolower((string) ($svc->product?->auto_setup ?? 'payment'));

            if (!$manual && $autoSetup === 'manual') {
                $awaitingManual = true;
                continue;
            }

            if ($svc->product && $svc->product->server_type) {
                // createAccount activates the service and fires ServiceActivated
                // on success; on failure it queues a retry and the service
                // stays pending (no "active but never provisioned" state).
                $result = $this->provisioning->createAccount($svc);
                if ($result['success'] ?? false) {
                    Log::info('Auto-provisioned service #' . $svc->id . ' on order accept');
                } else {
                    Log::error('Auto-provision failed for service #' . $svc->id . ': ' . ($result['message'] ?? 'unknown'));
                }
            } else {
                // No server module involved — plain activation.
                $svc->update([
                    'status'            => ServiceStatus::Active->value,
                    'registration_date' => $svc->registration_date ?? now()->toDateString(),
                ]);
            }
        }

        // Activate pending domains on this order
        Domain::where('order_id', $order->id)
            ->where('status', DomainStatus::Pending->value)
            ->update(['status' => DomainStatus::Active->value]);

        if ($awaitingManual) {
            // Keep the order pending so it shows up for admin review.
            run_hook('PendingOrder', ['order' => $order]);
            app(NotificationService::class)->dispatch('order.awaiting_acceptance', [
                'event_type' => 'order.awaiting_acceptance',
                'subject'    => 'Order awaiting manual acceptance',
                'message'    => "Order #{$order->order_num} is paid but contains products that require manual acceptance.",
                'order_id'   => $order->id,
                'order_num'  => $order->order_num,
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
                    'status'           => ServiceStatus::Cancelled->value,
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

    /**
     * Mark an order as fraud and suspend all related services.
     */
    public function markFraud(Order $order): Order
    {
        run_hook('FraudOrder', ['order' => $order]);

        return DB::transaction(function () use ($order) {
            $order->update([
                'status'       => OrderStatus::Fraud->value,
                'fraud_module' => 'manual',
                'fraud_output' => 'Manually marked as fraud by admin on ' . now()->toDateTimeString(),
            ]);

            Service::where('order_id', $order->id)
                ->where('status', ServiceStatus::Active->value)
                ->update([
                    'status'             => ServiceStatus::Suspended->value,
                    'suspension_date'    => now()->toDateString(),
                    'suspension_reason'  => 'Order marked as fraud',
                ]);

            // Cancel unpaid invoice
            if ($order->invoice_id) {
                $invoice = $order->invoice;
                if ($invoice && in_array($invoice->status, [InvoiceStatus::Unpaid->value, InvoiceStatus::Overdue->value])) {
                    $this->invoiceService->cancelInvoice($invoice);
                }
            }

            return $order->fresh();
        });
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
            'client_id'            => $client->id,
            'order_id'             => $order->id,
            'product_id'           => $item['product_id'] ?? null,
            'server_id'            => $item['server_id'] ?? null,
            'domain'               => $item['domain'] ?? null,
            'payment_method'       => $order->payment_method,
            'qty'                  => $item['qty'] ?? 1,
            'first_payment_amount' => $item['first_payment_amount'] ?? $item['amount'] ?? 0,
            'amount'               => $item['amount'] ?? 0,
            'billing_cycle'        => $billingCycle,
            'next_due_date'        => $this->calculateNextDueDate($billingCycle),
            'registration_date'    => now()->toDateString(),
            'status'               => ServiceStatus::Pending->value,
            'username'             => $item['username'] ?? null,
            'notes'                => $item['notes'] ?? null,
        ]);
    }

    private function createDomainForOrder(Order $order, Client $client, array $item): Domain
    {
        return Domain::create([
            'client_id'          => $client->id,
            'order_id'           => $order->id,
            'domain'             => $item['domain'] ?? '',
            'type'               => $item['domain_type'] ?? 'register',
            'registrar'          => $item['registrar'] ?? null,
            'registration_date'  => now()->toDateString(),
            'expiry_date'        => now()->addYear()->toDateString(),
            'status'             => DomainStatus::Pending->value,
            'recurring_amount'   => $item['amount'] ?? 0,
            'payment_method'     => $order->payment_method,
        ]);
    }

    private function calculateNextDueDate(string $billingCycle): string
    {
        return match (strtolower($billingCycle)) {
            'monthly'        => now()->addMonth()->toDateString(),
            'quarterly'      => now()->addMonths(3)->toDateString(),
            'semi-annually'  => now()->addMonths(6)->toDateString(),
            'annually'       => now()->addYear()->toDateString(),
            'biennially'     => now()->addYears(2)->toDateString(),
            'triennially'    => now()->addYears(3)->toDateString(),
            default          => now()->addMonth()->toDateString(),
        };
    }

    private function buildServiceDescription(Service $service, array $item): string
    {
        $product = $service->product;
        $name    = $product?->name ?? ($item['description'] ?? 'Hosting Service');
        $cycle   = $service->billing_cycle ?? 'Monthly';
        $domain  = $service->domain ? " — {$service->domain}" : '';

        return "{$name} ({$cycle}){$domain}";
    }

    private function generateOrderNumber(): string
    {
        return strtoupper(Str::random(8));
    }
}
