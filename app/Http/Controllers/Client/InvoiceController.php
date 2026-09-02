<?php

namespace App\Http\Controllers\Client;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesClient;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use App\Models\PaymentNotification;
use App\Services\InvoicePdfService;
use App\Services\Module\ModuleRegistry;
use App\Services\NotificationService;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    use ResolvesClient;

    public function index()
    {
        $invoices = Invoice::with('items')
            ->excludeSettledProformas()
            ->where('client_id', $this->getClientId())
            ->orderBy('id', 'desc')
            ->paginate(25);

        return view('client.invoices.index', compact('invoices'));
    }

    public function show(Invoice $invoice)
    {
        abort_if($invoice->client_id !== $this->getClientId(), 403);
        $invoice->load('items');

        // The value is encrypted at rest, so it is compared after it comes
        // back rather than in the query: a where() against the column can never
        // match, and this page quietly showed bank transfer only.
        // Switched on is not the same as ready: a gateway missing its keys is
        // not offered, or the customer picks it and the payment fails at the
        // last step.
        $gateways = app(ModuleRegistry::class)->usableGateways();

        if (empty($gateways)) {
            $gateways = ['banktransfer'];
        }

        $gatewayForms = [];
        $gatewayLabels = [];
        if (in_array(strtolower($invoice->status), ['unpaid', 'overdue', 'partially_paid'])) {
            $registry = app(ModuleRegistry::class);
            foreach ($gateways as $gw) {
                $module = $registry->getGatewayModule($gw);
                if ($module) {
                    try {
                        $gatewayForms[$gw] = $module->getPaymentForm($invoice);
                        $gatewayLabels[$gw] = payment_method_label($gw);
                    } catch (\Throwable $e) {
                        $gatewayLabels[$gw] = ucfirst($gw);
                    }
                } else {
                    $gatewayLabels[$gw] = ucwords(str_replace('_', ' ', $gw));
                }
            }
        }

        $pendingNotification = PaymentNotification::where('invoice_id', $invoice->id)
            ->pending()
            ->latest()
            ->first();

        $balance = app(PaymentService::class)->balance($invoice);

        return view('client.invoices.show', compact('invoice', 'gateways', 'gatewayForms', 'gatewayLabels', 'pendingNotification', 'balance'));
    }

    /**
     * Client reports an offline payment (bank transfer) for review.
     * Puts the invoice into payment_pending and alerts the admins.
     */
    public function submitPaymentNotification(Request $request, Invoice $invoice)
    {
        abort_if($invoice->client_id !== $this->getClientId(), 403);

        if (!in_array(strtolower((string) $invoice->status), ['unpaid', 'overdue', 'partially_paid', 'payment_pending'])) {
            return back()->with('error', __('messages.error.invoice_not_awaiting_payment'));
        }

        if (PaymentNotification::where('invoice_id', $invoice->id)->pending()->exists()) {
            return back()->with('info', __('messages.info.payment_notification_already_pending'));
        }

        $validated = $request->validate([
            'sender_name'   => ['required', 'string', 'max:255'],
            'bank_name'     => ['nullable', 'string', 'max:255'],
            'amount'        => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'transfer_date' => ['required', 'date', 'before_or_equal:today'],
            'reference'     => ['nullable', 'string', 'max:255'],
            'client_note'   => ['nullable', 'string', 'max:2000'],
            'receipt'       => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $receiptPath = null;
        if ($request->hasFile('receipt')) {
            $receiptPath = $request->file('receipt')->store("payment-receipts/{$invoice->id}", 'local');
        }

        $notification = PaymentNotification::create([
            'invoice_id'    => $invoice->id,
            'client_id'     => $invoice->client_id,
            'gateway'       => 'banktransfer',
            'sender_name'   => $validated['sender_name'],
            'bank_name'     => $validated['bank_name'] ?? null,
            'amount'        => $validated['amount'],
            'transfer_date' => $validated['transfer_date'],
            'reference'     => $validated['reference'] ?? null,
            'receipt_path'  => $receiptPath,
            'client_note'   => $validated['client_note'] ?? null,
            'status'        => 'pending',
        ]);

        $invoice->update(['status' => InvoiceStatus::PaymentPending->value]);

        app(NotificationService::class)->dispatch('payment.notification_received', [
            'event_type' => 'payment.notification_received',
            'subject'    => 'Bank transfer notification received',
            'message'    => "Client reported a bank transfer of {$notification->amount} for invoice #{$invoice->invoice_num}. Awaiting review.",
            'invoice_id' => $invoice->id,
            'notification_id' => $notification->id,
        ]);

        return back()->with('success', __('messages.success.payment_notification_submitted'));
    }

    public function downloadPdf(Invoice $invoice, InvoicePdfService $pdfService)
    {
        abort_if($invoice->client_id !== $this->getClientId(), 403);

        return $pdfService->download($invoice);
    }

}
