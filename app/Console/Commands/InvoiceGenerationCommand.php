<?php
namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Enums\ServiceStatus;
use App\Models\Service;
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

        $this->info("Generated {$count} invoices.");
        return Command::SUCCESS;
    }
}
