<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Promotion;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InvoiceGenerationService
{
    public function __construct(
        protected InvoiceService $invoiceService
    ) {}

    /**
     * Scan services where next_due_date is within the generation window,
     * group by client, and create one invoice per client for all due items.
     *
     * @return array{generated: int, skipped: int, errors: int, invoice_ids: int[]}
     */
    public function generateDueInvoices(): array
    {
        $daysAhead = (int) config('billing.invoice_days_before_due', 14);
        $cutoff    = now()->addDays($daysAhead)->endOfDay();

        // Only active services that are due for renewal and not already invoiced recently
        $services = Service::with('client', 'product')
            ->where('status', 'Active')
            ->whereNotNull('next_due_date')
            ->where('next_due_date', '<=', $cutoff)
            ->whereHas('client')
            ->get();

        $grouped  = $services->groupBy('client_id');
        $summary  = ['generated' => 0, 'skipped' => 0, 'errors' => 0, 'invoice_ids' => []];

        foreach ($grouped as $clientId => $clientServices) {
            $client = $clientServices->first()->client;

            if (!$client) {
                $summary['skipped']++;
                continue;
            }

            try {
                $invoice = $this->generateForServices($client, $clientServices->all());

                if ($invoice) {
                    $summary['generated']++;
                    $summary['invoice_ids'][] = $invoice->id;
                } else {
                    $summary['skipped']++;
                }
            } catch (\Throwable $e) {
                Log::error('Invoice generation failed for client ' . $clientId . ': ' . $e->getMessage());
                $summary['errors']++;
            }
        }

        return $summary;
    }

    /**
     * Generate an invoice for a specific service.
     */
    public function generateForService(Service $service): ?Invoice
    {
        $service->loadMissing('client', 'product');

        if (!$service->client) {
            return null;
        }

        return $this->generateForServices($service->client, [$service]);
    }

    /**
     * Apply a promotion code to an invoice.
     * Validates the code, applies the discount, and increments usage counter.
     *
     * @return bool True if promotion was applied successfully.
     */
    public function applyPromotion(Invoice $invoice, string $promoCode): bool
    {
        $promo = Promotion::where('code', $promoCode)->first();

        if (!$promo || !$promo->isValid()) {
            return false;
        }

        $invoice->loadMissing('items');

        return DB::transaction(function () use ($invoice, $promo) {
            $subtotal = (float) $invoice->subtotal;

            if ($subtotal <= 0) {
                return false;
            }

            // Calculate discount amount
            $discount = match ($promo->type) {
                'percentage' => round($subtotal * ($promo->value / 100), 2),
                'fixed'      => min((float) $promo->value, $subtotal),
                default      => 0.0,
            };

            if ($discount <= 0) {
                return false;
            }

            // Apply as credit on the invoice
            $newCredit = (float) $invoice->credit + $discount;
            $newTotal  = max(0, $subtotal + (float) $invoice->tax + (float) $invoice->tax2 - $newCredit);

            $invoice->update([
                'credit'     => $newCredit,
                'total'      => $newTotal,
                'notes'      => trim(($invoice->notes ?? '') . "\nPromo applied: {$promo->code} (-\${$discount})"),
            ]);

            // Increment promo usage counter
            $promo->increment('uses');

            return true;
        });
    }

    /**
     * Mark overdue invoices (unpaid and past due date).
     *
     * @return int Number of invoices marked as Overdue.
     */
    public function markOverdueInvoices(): int
    {
        return Invoice::where('status', 'Unpaid')
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->startOfDay())
            ->update(['status' => 'Overdue']);
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Build line items from a list of services and create a single invoice.
     *
     * @param  Service[]  $services
     */
    private function generateForServices(\App\Models\Client $client, array $services): ?Invoice
    {
        if (empty($services)) {
            return null;
        }

        $items = [];

        foreach ($services as $service) {
            $service->loadMissing('product');
            $productName = $service->product->name ?? 'Hosting Service';
            $billingCycle = $service->billing_cycle ?? 'Monthly';
            $dueDate = $service->next_due_date instanceof Carbon
                ? $service->next_due_date
                : Carbon::parse($service->next_due_date);

            $items[] = [
                'type'        => 'Hosting',
                'rel_id'      => $service->id,
                'description' => "{$productName} ({$billingCycle}) — {$service->domain} — Due: {$dueDate->format('d M Y')}",
                'amount'      => (float) $service->amount,
                'taxed'       => $service->product->tax ?? true,
                'due_date'    => $dueDate->toDateString(),
            ];
        }

        $options = [
            'due_date' => now()->addDays((int) config('billing.invoice_due_days', 14))->toDateString(),
            'notes'    => 'Auto-generated renewal invoice.',
        ];

        return $this->invoiceService->createInvoice($client, $items, $options);
    }
}
