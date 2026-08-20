<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\BulkMassMail;
use App\Mail\InvoiceCreatedMail;
use App\Mail\PaymentReminderMail;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Service;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Services\ProvisioningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class BulkActionController extends Controller
{
    public function massEmailForm()
    {
        $clients = Client::orderBy('first_name')->get();

        return view('admin.bulk.mass-email', compact('clients'));
    }

    public function massEmail(Request $request)
    {
        $validated = $request->validate([
            'client_ids' => 'required|array|min:1',
            'client_ids.*' => 'exists:clients,id',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        $clients = Client::whereIn('id', $validated['client_ids'])->get();
        $queued = 0;

        foreach ($clients as $client) {
            if (! $client->email) {
                continue;
            }
            try {
                $name = trim("{$client->first_name} {$client->last_name}") ?: $client->email;
                Mail::to($client->email)->queue(
                    new BulkMassMail($validated['subject'], $validated['message'], $name)
                );
                $queued++;
            } catch (\Throwable $e) {
                Log::error("Bulk email queue failed for client #{$client->id}: ".$e->getMessage());
            }
        }

        return back()->with('success', __('admin.messages.emails_sent', ['count' => $queued]));
    }

    public function bulkInvoice(Request $request, InvoiceService $invoiceService)
    {
        $validated = $request->validate([
            'client_ids' => 'required|array|min:1',
            'client_ids.*' => 'exists:clients,id',
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'due_date' => 'required|date|after:today',
        ]);
        $created = 0;

        foreach ($validated['client_ids'] as $clientId) {
            $client = Client::find($clientId);
            if (! $client) {
                continue;
            }
            try {
                $invoiceService->createInvoice($client, [
                    ['description' => $validated['description'], 'amount' => $validated['amount'], 'qty' => 1],
                ], ['due_date' => $validated['due_date']]);
                $created++;
            } catch (\Throwable $e) {
                Log::error("Bulk invoice failed for client #{$clientId}: ".$e->getMessage());
            }
        }

        return back()->with('success', __('admin.messages.invoices_created', ['count' => $created]));
    }

    /**
     * Run one action across the selected invoices: mark paid, cancel, email
     * or send a payment reminder.
     */
    public function bulkInvoiceAction(Request $request, InvoiceService $invoiceService)
    {
        $validated = $request->validate([
            'invoice_ids' => 'required|array|min:1',
            'invoice_ids.*' => 'exists:invoices,id',
            'action' => 'required|in:paid,cancel,send,remind',
        ]);

        $invoices = Invoice::with('client')->whereIn('id', $validated['invoice_ids'])->get();
        $action = $validated['action'];
        $processed = 0;

        foreach ($invoices as $invoice) {
            if ($this->applyInvoiceAction($invoice, $action, $invoiceService)) {
                $processed++;
            }
        }

        return back()->with('success', __('admin.messages.bulk_invoice_action', ['count' => $processed]));
    }

    private function applyInvoiceAction(Invoice $invoice, string $action, InvoiceService $invoiceService): bool
    {
        try {
            return match ($action) {
                'paid' => $this->bulkPaid($invoice),
                'cancel' => $this->bulkCancel($invoice, $invoiceService),
                'send' => $this->bulkSend($invoice),
                'remind' => $this->bulkRemind($invoice),
                default => false,
            };
        } catch (\Throwable $e) {
            Log::error("Bulk invoice {$action} failed for #{$invoice->id}: {$e->getMessage()}");

            return false;
        }
    }

    private function bulkPaid(Invoice $invoice): bool
    {
        if (strtolower((string) $invoice->status) === 'paid') {
            return false;
        }

        app(PaymentService::class)->applyPayment($invoice, 'manual', null, null);

        return true;
    }

    private function bulkCancel(Invoice $invoice, InvoiceService $invoiceService): bool
    {
        if (strtolower((string) $invoice->status) === 'paid') {
            return false;
        }

        $invoiceService->cancelInvoice($invoice);

        return true;
    }

    private function bulkSend(Invoice $invoice): bool
    {
        if (! $invoice->client?->email) {
            return false;
        }

        Mail::to($invoice->client->email)->queue(new InvoiceCreatedMail($invoice));

        return true;
    }

    private function bulkRemind(Invoice $invoice): bool
    {
        if (! $invoice->client?->email) {
            return false;
        }

        $daysOffset = $invoice->due_date
            ? (int) now()->startOfDay()->diffInDays($invoice->due_date->startOfDay(), false)
            : 0;

        Mail::to($invoice->client->email)->queue(new PaymentReminderMail($invoice, $daysOffset));

        return true;
    }

    public function bulkServiceUpdate(Request $request, ProvisioningService $provisioning)
    {
        $validated = $request->validate([
            'service_ids' => 'required|array|min:1',
            'service_ids.*' => 'exists:services,id',
            'status' => 'required|in:active,suspended,terminated,cancelled',
        ]);

        $status = $validated['status'];
        $updated = 0;
        $failed = 0;

        // One at a time on purpose: a query-builder update fires no model
        // events, and ending a service has to take its addons with it.
        foreach (Service::whereIn('id', $validated['service_ids'])->get() as $service) {
            // A service that lives on a panel has to be told, exactly as the
            // single-service screen does. Writing the status straight to the
            // database left the account serving the site while the panel
            // claimed it was suspended - or, on terminate, gave away hosting
            // for free forever.
            $result = $this->applyOnServer($provisioning, $service, $status);

            if ($result !== null) {
                if ($result['success'] ?? false) {
                    $updated++;
                } else {
                    // ProvisioningService already queued a retry and left the
                    // status alone; saying "done" here would be a lie.
                    $failed++;
                }

                continue;
            }

            $changes = ['status' => $status];

            if ($status === 'suspended' && ! $service->suspension_date) {
                $changes['suspension_date'] = now();
            }

            if (in_array($status, ['terminated', 'cancelled'], true) && ! $service->termination_date) {
                $changes['termination_date'] = now();
            }

            if ($status === 'active') {
                $changes['suspension_date'] = null;
                $changes['suspension_reason'] = null;
            }

            $service->update($changes);
            $updated++;
        }

        $message = __('admin.messages.services_updated', ['count' => $updated, 'status' => $status]);

        if ($failed > 0) {
            return back()
                ->with('success', $message)
                ->with('warning', __('admin.messages.services_update_failed', ['count' => $failed]));
        }

        return back()->with('success', $message);
    }

    /**
     * Carry the new status out to the panel the service runs on.
     *
     * Returns null when there is nothing to send - no server behind the
     * service, cancelling (a billing decision), or a status the account is
     * already in - and the caller falls back to updating the record.
     */
    private function applyOnServer(ProvisioningService $provisioning, Service $service, string $status): ?array
    {
        // Without a server of its own the product's module would still resolve
        // and act on somebody else's panel, so the server itself is the test.
        if (! $service->server_id) {
            return null;
        }

        $current = strtolower((string) $service->status);

        if ($current === $status) {
            return null;
        }

        return match ($status) {
            'suspended' => $provisioning->suspendAccount($service, __('admin.messages.bulk_suspension_reason')),
            'terminated' => $provisioning->terminateAccount($service),
            'active' => $current === 'suspended' ? $provisioning->unsuspendAccount($service) : null,
            default => null,
        };
    }
}
