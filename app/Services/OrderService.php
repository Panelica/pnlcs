<?php

namespace App\Services;

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
        return DB::transaction(function () use ($client, $items, $paymentMethod, $promoCode) {
            $orderNum  = $this->generateOrderNumber();
            $totalAmount = array_sum(array_column($items, 'amount'));

            $order = Order::create([
                'order_num'      => $orderNum,
                'client_id'      => $client->id,
                'date'           => now()->toDateString(),
                'amount'         => $totalAmount,
                'payment_method' => $paymentMethod,
                'status'         => 'Pending',
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

            // Auto-provision: trigger module create for each service
            $services = Service::where("order_id", $order->id)->where("status", "Active")->with("product")->get();
            foreach ($services as $svc) {
                if ($svc->product && $svc->product->server_type) {
                    try {
                        $this->provisioning->createAccount($svc);
                        Log::info("Auto-provisioned service #" . $svc->id);
                    } catch (\Throwable $e) {
                        Log::error("Auto-provision failed for service #" . $svc->id . ": " . $e->getMessage());
                    }
                }
            }

            return $order->fresh();
        });
    }

    /**
     * Accept a pending order: transition to Active and activate all services.
     */
    public function acceptOrder(Order $order): Order
    {
        if ($order->status === 'Active') {
            return $order;
        }

        return DB::transaction(function () use ($order) {
            $order->update(['status' => 'Active']);

            // Activate all pending services on this order
            Service::where('order_id', $order->id)
                ->where('status', 'Pending')
                ->update([
                    'status'            => 'Active',
                    'registration_date' => now()->toDateString(),
                ]);

            // Activate pending domains on this order
            Domain::where('order_id', $order->id)
                ->where('status', 'Pending')
                ->update(['status' => 'Active']);


            // Auto-provision activated services
            $activatedServices = Service::where("order_id", $order->id)
                ->where("status", "Active")
                ->with("product")
                ->get();
            foreach ($activatedServices as $svc) {
                if ($svc->product && $svc->product->server_type) {
                    try {
                        $this->provisioning->createAccount($svc);
                        Log::info("Auto-provisioned service #" . $svc->id . " on order accept");
                    } catch (\Throwable $e) {
                        Log::error("Auto-provision failed for service #" . $svc->id . ": " . $e->getMessage());
                    }
                }
            }
            return $order->fresh();
        });
    }

    /**
     * Cancel an order and terminate all related services.
     */
    public function cancelOrder(Order $order): Order
    {
        if (in_array($order->status, ['Cancelled', 'Fraud'])) {
            return $order;
        }

        return DB::transaction(function () use ($order) {
            $order->update(['status' => 'Cancelled']);

            Service::where('order_id', $order->id)
                ->whereNotIn('status', ['Terminated', 'Cancelled'])
                ->update([
                    'status'           => 'Cancelled',
                    'termination_date' => now()->toDateString(),
                ]);

            Domain::where('order_id', $order->id)
                ->whereNotIn('status', ['Expired', 'Cancelled'])
                ->update(['status' => 'Cancelled']);

            // Cancel the linked invoice if still unpaid
            if ($order->invoice_id) {
                $invoice = $order->invoice;
                if ($invoice && in_array($invoice->status, ['Unpaid', 'Overdue'])) {
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
        return DB::transaction(function () use ($order) {
            $order->update([
                'status'       => 'Fraud',
                'fraud_module' => 'manual',
                'fraud_output' => 'Manually marked as fraud by admin on ' . now()->toDateTimeString(),
            ]);

            Service::where('order_id', $order->id)
                ->where('status', 'Active')
                ->update([
                    'status'             => 'Suspended',
                    'suspension_date'    => now()->toDateString(),
                    'suspension_reason'  => 'Order marked as fraud',
                ]);

            // Cancel unpaid invoice
            if ($order->invoice_id) {
                $invoice = $order->invoice;
                if ($invoice && in_array($invoice->status, ['Unpaid', 'Overdue'])) {
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
            'status'               => 'Pending',
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
            'status'             => 'Pending',
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
