<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Enums\ServiceStatus;
use App\Models\Domain;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Service;
use App\Services\AddonService;
use App\Services\InvoiceGenerationService;
use App\Services\InvoiceService;
use Illuminate\Console\Command;

class InvoiceGenerationCommand extends Command
{
    protected $signature = 'pnlcs:generate-invoices';

    protected $description = 'Generate invoices for services due within the next billing period';

    public function handle(): int
    {
        $invoiceService = app(InvoiceService::class);
        $generator = app(InvoiceGenerationService::class);
        $addonService = app(AddonService::class);
        $cutoff = now()->addDays(14);
        $billedAddonIds = [];

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
            if ($existingInvoice) {
                continue;
            }

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
                'description' => ($service->product->name ?? 'Service').' - '.($service->domain ?? ''),
                'amount' => $service->amount,
                'taxed' => true,
                'due_date' => $service->next_due_date?->format('Y-m-d'),
            ]);

            // Disk and bandwidth overage. The charge was only ever assembled by
            // InvoiceGenerationService, which this command does not call, so a
            // product with overage enabled never billed a single cent of it.
            foreach ($generator->calculateOverageItems($service) as $overage) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'client_id' => $service->client_id,
                    'type' => $overage['type'],
                    'rel_id' => $overage['rel_id'],
                    'description' => $overage['description'],
                    'amount' => $overage['amount'],
                    'taxed' => $overage['taxed'],
                    'due_date' => $service->next_due_date?->format('Y-m-d'),
                ]);
            }

            // Addons renew with the service they belong to when both fall due.
            foreach ($addonService->dueQuery($cutoff)->where('service_id', $service->id)->get() as $serviceAddon) {
                $line = $addonService->lineItem($serviceAddon);
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'client_id' => $service->client_id,
                    'type' => $line['type'],
                    'rel_id' => $line['rel_id'],
                    'description' => $line['description'],
                    'amount' => $line['amount'],
                    'taxed' => $line['taxed'],
                    'due_date' => $line['due_date'],
                ]);
                $billedAddonIds[] = $serviceAddon->id;
            }

            $invoiceService->recalculateTotals($invoice->fresh());
            $count++;
        }

        // Addons bought after the fact come due on their own dates, so they
        // have to be billable without the service being due.
        foreach ($addonService->dueQuery($cutoff)->whereNotIn('id', $billedAddonIds ?: [0])->get() as $serviceAddon) {
            $line = $addonService->lineItem($serviceAddon);

            $invoice = Invoice::create([
                'client_id' => $serviceAddon->client_id,
                'invoice_num' => $invoiceService->generateInvoiceNumber(),
                'date' => now()->format('Y-m-d'),
                'due_date' => $line['due_date'] ?? now()->addDays(14)->format('Y-m-d'),
                'status' => InvoiceStatus::Unpaid->value,
            ]);

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'client_id' => $serviceAddon->client_id,
                'type' => $line['type'],
                'rel_id' => $line['rel_id'],
                'description' => $line['description'],
                'amount' => $line['amount'],
                'taxed' => $line['taxed'],
                'due_date' => $line['due_date'],
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
            if ($existing) {
                continue;
            }

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
                'description' => 'Domain Renewal: '.$domain->domain.' ('.((int) ($domain->registration_period ?? 1)).'y)',
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
