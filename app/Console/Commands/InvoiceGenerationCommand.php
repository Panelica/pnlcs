<?php
namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Enums\ServiceStatus;
use App\Models\Service;
use App\Models\Domain;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\InvoiceService;
use Illuminate\Console\Command;

class InvoiceGenerationCommand extends Command
{
    protected $signature = 'pnlcs:generate-invoices';
    protected $description = 'Generate invoices for services due within the next billing period';

    public function handle(): int
    {
        $invoiceService = app(InvoiceService::class);

        $services = Service::where('status', ServiceStatus::Active->value)
            ->whereNotNull('next_due_date')
            ->where('next_due_date', '<=', now()->addDays(14))
            ->where('amount', '>', 0)
            ->get();

        $count = 0;
        foreach ($services as $service) {
            $existingInvoice = Invoice::where('client_id', $service->client_id)
                ->where('status', InvoiceStatus::Unpaid->value)
                ->whereHas('items', fn ($q) => $q->where('type', 'Hosting')->where('rel_id', $service->id))
                ->exists();
            if ($existingInvoice) continue;

            $invoice = Invoice::create([
                'client_id' => $service->client_id,
                'invoice_num' => $invoiceService->generateInvoiceNumber(),
                'date' => now()->format('Y-m-d'),
                'due_date' => $service->next_due_date?->format('Y-m-d') ?? now()->addDays(14)->format('Y-m-d'),
                'status' => InvoiceStatus::Unpaid->value,
                'payment_method' => $service->payment_method,
            ]);

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'client_id' => $service->client_id,
                'type' => 'Hosting',
                'rel_id' => $service->id,
                'description' => ($service->product->name ?? 'Service') . ' - ' . ($service->domain ?? ''),
                'amount' => $service->amount,
                'taxed' => true,
                'due_date' => $service->next_due_date?->format('Y-m-d'),
            ]);

            $invoiceService->recalculateTotals($invoice->fresh());
            $count++;
        }

        // Domain renewals — the domains table carries its own next_due_date /
        // recurring_amount, independent of services.
        $domains = Domain::where('status', 'active')
            ->whereNotNull('next_due_date')
            ->where('next_due_date', '<=', now()->addDays(14))
            ->where('recurring_amount', '>', 0)
            ->get();

        foreach ($domains as $domain) {
            $existing = Invoice::whereIn('status', [InvoiceStatus::Unpaid->value, InvoiceStatus::Overdue->value])
                ->where('client_id', $domain->client_id)
                ->whereHas('items', fn ($q) => $q->where('type', 'Domain')->where('rel_id', $domain->id))
                ->exists();
            if ($existing) continue;

            $invoice = Invoice::create([
                'client_id' => $domain->client_id,
                'invoice_num' => $invoiceService->generateInvoiceNumber(),
                'date' => now()->format('Y-m-d'),
                'due_date' => $domain->next_due_date?->format('Y-m-d') ?? now()->addDays(14)->format('Y-m-d'),
                'status' => InvoiceStatus::Unpaid->value,
                'payment_method' => $domain->payment_method,
            ]);

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'client_id' => $domain->client_id,
                'type' => 'Domain',
                'rel_id' => $domain->id,
                'description' => 'Domain Renewal: ' . $domain->domain . ' (' . ((int) ($domain->registration_period ?? 1)) . 'y)',
                'amount' => $domain->recurring_amount,
                'taxed' => true,
                'due_date' => $domain->next_due_date?->format('Y-m-d'),
            ]);

            $invoiceService->recalculateTotals($invoice->fresh());
            $count++;
        }

        $this->info("Generated {$count} invoices.");
        return Command::SUCCESS;
    }
}
