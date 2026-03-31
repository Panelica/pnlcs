<?php
namespace App\Console\Commands;

use App\Models\Service;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Console\Command;

class InvoiceGenerationCommand extends Command
{
    protected $signature = "pnlcs:generate-invoices";
    protected $description = "Generate invoices for services due within the next billing period";

    public function handle(): int
    {
        $services = Service::where("status", "active")
            ->whereNotNull("next_due_date")
            ->where("next_due_date", "<=", now()->addDays(14))
            ->where("amount", ">", 0)
            ->get();

        $count = 0;
        foreach ($services as $service) {
            $existingInvoice = Invoice::where("client_id", $service->client_id)
                ->where("status", "unpaid")
                ->whereHas("items", fn ($q) => $q->where("type", "Hosting")->where("rel_id", $service->id))
                ->exists();
            if ($existingInvoice) continue;

            $invoice = Invoice::create([
                "client_id" => $service->client_id,
                "date" => now()->format("Y-m-d"),
                "due_date" => $service->next_due_date->format("Y-m-d"),
                "status" => "unpaid",
                "payment_method" => $service->payment_method,
            ]);

            InvoiceItem::create([
                "invoice_id" => $invoice->id,
                "client_id" => $service->client_id,
                "type" => "Hosting",
                "rel_id" => $service->id,
                "description" => ($service->product->name ?? "Service") . " - " . ($service->domain ?? ""),
                "amount" => $service->amount,
                "taxed" => true,
                "due_date" => $service->next_due_date->format("Y-m-d"),
            ]);

            $invoice->update(["subtotal" => $service->amount, "total" => $service->amount]);
            $count++;
        }

        $this->info("Generated {$count} invoices.");
        return Command::SUCCESS;
    }
}
