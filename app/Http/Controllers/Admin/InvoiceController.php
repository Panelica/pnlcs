<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Traits\CsvExportable;
use App\Mail\InvoiceCreatedMail;
use App\Mail\PaymentReminderMail;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Setting;
use App\Services\InvoicePdfService;
use App\Services\InvoiceService;
use App\Services\Module\ModuleRegistry;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends Controller
{
    use CsvExportable;

    public function __construct(
        protected InvoiceService $invoiceService,
        protected PaymentService $paymentService
    ) {}

    public function index(Request $request): View
    {
        $query = Invoice::with('client');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('invoice_num', 'like', "%{$request->search}%")
                    ->orWhereHas('client', fn ($c) => $c->where('first_name', 'like', "%{$request->search}%")
                        ->orWhere('last_name', 'like', "%{$request->search}%")
                    );
            });
        }

        $invoices = $query->orderBy('created_at', 'desc')->paginate(25);

        return view('admin.invoices.index', compact('invoices'));
    }

    public function show(Invoice $invoice): View
    {
        $invoice->load('client', 'items', 'transactions');

        return view('admin.invoices.show', compact('invoice'));
    }

    /**
     * Update a single line item and recalculate the invoice totals.
     */
    public function updateItem(Request $request, Invoice $invoice, InvoiceItem $item): RedirectResponse
    {
        abort_if($item->invoice_id !== $invoice->id, 404);

        $validated = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'qty' => ['required', 'integer', 'min:1', 'max:999999'],
            'amount' => ['required', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $taxRate = isset($validated['tax_rate']) && $validated['tax_rate'] !== ''
            ? (float) $validated['tax_rate']
            : 0.0;

        // Give every line an explicit rate so a legacy invoice edited once
        // stays consistent: lines without a rate would otherwise stop being
        // taxed the moment any other line carries its own rate.
        $fallback = (float) $invoice->tax_rate;
        $invoice->items()->whereNull('tax_rate')->get()->each(function (InvoiceItem $legacy) use ($fallback) {
            $legacy->update(['tax_rate' => $legacy->taxed ? $fallback : 0.0]);
        });

        $item->update([
            'description' => $validated['description'],
            'qty' => (int) $validated['qty'],
            'amount' => (float) $validated['amount'],
            'tax_rate' => $taxRate,
            'taxed' => $taxRate > 0,
        ]);

        $this->invoiceService->recalculateTotals($invoice);

        return back()->with('success', __('admin.invoices.item_updated'));
    }

    /**
     * Email the invoice to the client.
     */
    public function sendInvoice(Invoice $invoice): RedirectResponse
    {
        abort_if(! $invoice->client?->email, 422, 'Client has no email address.');

        Mail::to($invoice->client->email)->queue(new InvoiceCreatedMail($invoice));

        return back()->with('success', __('admin.invoices.email_sent'));
    }

    /**
     * Send a payment reminder for this invoice (positive = due in N days,
     * negative = N days overdue).
     */
    public function sendReminder(Invoice $invoice): RedirectResponse
    {
        abort_if(! $invoice->client?->email, 422, 'Client has no email address.');

        $daysOffset = $invoice->due_date
            ? (int) now()->startOfDay()->diffInDays($invoice->due_date->startOfDay(), false)
            : 0;

        Mail::to($invoice->client->email)->queue(new PaymentReminderMail($invoice, $daysOffset));

        return back()->with('success', __('admin.invoices.reminder_sent'));
    }

    /**
     * Show the invoice creation form.
     */
    public function create(Request $request): View
    {
        $clients = Client::orderBy('first_name')->get();
        // r139-gateways: this box picks the gateway the invoice is paid
        // through, and it was filled from the payment_methods table - every
        // customer's stored method, unscoped, labelled with that customer's own
        // description. An operator raising an invoice for one customer was
        // shown what other customers had named their bank accounts, and the
        // list was wrong besides: three customers with a bank account gave
        // three identical options, and an installation where nobody had saved
        // one offered no gateway at all.
        $gateways = app(ModuleRegistry::class)->usableGateways();

        $selectedClient = $request->filled('client_id')
            ? Client::find($request->client_id)
            : null;

        $defaultPaymentMethod = $selectedClient?->default_payment_method;

        $dueDays = (int) Setting::get('InvoiceDueDays', 14);

        // Cheapest sold cycle stands in for the product's money value, so the
        // builder can pre-fill the amount when a product is picked.
        $products = Product::active()->with('pricing')->get()->map(function (Product $p) {
            $cycles = $p->pricedCycles();

            return [
                'name' => $p->name,
                'amount' => $cycles ? min($cycles) : null,
                'cycle' => $cycles ? array_search(min($cycles), $cycles, true) : null,
                'taxed' => (bool) $p->tax,
            ];
        })->values();

        $defaultCurrency = Currency::getDefault();

        // Per-client applicable tax rate (level 1 only, shown in the summary
        // while building the invoice). Matches the engine: country+state.
        $invoiceService = app(InvoiceService::class);
        $clients = $clients->map(function (Client $client) use ($invoiceService) {
            $rule = $invoiceService->taxRuleFor($client, 1);

            return clone $client->setAttribute('billing_tax_rate', (float) ($rule?->tax_rate ?? 0))
                ->setAttribute('billing_tax_label', $rule?->name ?? '');
        });

        return view('admin.invoices.create', compact(
            'clients',
            'gateways',
            'selectedClient',
            'products',
            'defaultCurrency',
            'defaultPaymentMethod',
            'dueDays'
        ));
    }

    /**
     * Store a new invoice created from the admin form.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'date' => ['required', 'date'],
            'due_date' => ['required', 'date'],
            'payment_method' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.qty' => ['nullable', 'integer', 'min:1', 'max:999999'],
            'items.*.amount' => ['required', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $client = Client::findOrFail($validated['client_id']);

        $items = array_map(fn ($item) => [
            'type' => 'Other',
            'description' => $item['description'],
            'qty' => (int) ($item['qty'] ?? 1),
            'amount' => (float) $item['amount'],
            // Per-item VAT percentage; an empty field means untaxed.
            'tax_rate' => isset($item['tax_rate']) && $item['tax_rate'] !== '' ? (float) $item['tax_rate'] : 0.0,
        ], $validated['items']);

        $invoice = $this->invoiceService->createInvoice($client, $items, [
            'date' => $validated['date'],
            'due_date' => $validated['due_date'],
            'payment_method' => $validated['payment_method'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()
            ->route('admin.invoices.show', $invoice)
            ->with('success', __('admin.messages.invoice_created', ['num' => $invoice->invoice_num]));
    }

    /**
     * Mark an invoice as paid.
     */
    public function markPaid(Request $request, Invoice $invoice): RedirectResponse
    {
        if (strtolower((string) $invoice->status) === InvoiceStatus::Paid->value) {
            return back()->with('info', __('admin.messages.invoice_already_paid'));
        }

        $validated = $request->validate([
            'transaction_id' => ['nullable', 'string', 'max:255'],
            'gateway' => ['nullable', 'string', 'max:100'],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
        ]);

        // The form's amount field used to be silently ignored, marking the
        // invoice fully paid regardless of what the admin entered. Routing
        // through PaymentService keeps partial payments, overpayment-to-credit
        // and the InvoicePaid event chain consistent.
        $result = $this->paymentService->applyPayment(
            $invoice,
            $validated['gateway'] ?? 'manual',
            $validated['transaction_id'] ?? null,
            isset($validated['amount']) ? (float) $validated['amount'] : null
        );

        if (($result['balance'] ?? 0) > 0.009) {
            return back()->with('success', __('admin.messages.invoice_partially_paid', [
                'num' => $invoice->invoice_num,
                'balance' => number_format($result['balance'], 2),
            ]));
        }

        return back()->with('success', __('admin.messages.invoice_marked_paid', ['num' => $invoice->invoice_num]));
    }

    /**
     * Refund an invoice (full or partial) through the original gateway,
     * or offline for bank transfer / manual payments.
     */
    public function refund(Request $request, Invoice $invoice): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'reason' => ['nullable', 'string', 'max:500'],
            'gateway_refund' => ['nullable', 'boolean'],
        ]);

        $result = app(PaymentService::class)->refundInvoice(
            $invoice,
            $validated['amount'] ?? null,
            [
                'reason' => $validated['reason'] ?? null,
                'gateway_refund' => $request->boolean('gateway_refund', true),
            ]
        );

        return $result['success']
            ? back()->with('success', $result['message'])
            : back()->with('error', $result['message']);
    }

    /**
     * Cancel an invoice.
     */
    public function cancel(Invoice $invoice): RedirectResponse
    {
        if (strtolower((string) $invoice->status) === InvoiceStatus::Paid->value) {
            return back()->with('error', __('messages.error.paid_invoices_cannot_be_cancelled'));
        }

        $this->invoiceService->cancelInvoice($invoice);

        return back()->with('success', __('admin.messages.invoice_cancelled', ['num' => $invoice->invoice_num]));
    }

    /**
     * Export invoices as CSV.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $query = Invoice::with('client');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $invoices = $query->orderBy('id', 'asc')->get();

        $rows = $invoices->map(fn ($inv) => [
            $inv->id,
            $inv->invoice_num ?? '',
            $inv->client?->full_name ?? '',
            $inv->client?->email ?? '',
            $inv->status,
            $inv->subtotal ?? '0.00',
            $inv->tax_amount ?? '0.00',
            $inv->total ?? '0.00',
            $inv->payment_method ?? '',
            $inv->date?->format('Y-m-d') ?? '',
            $inv->due_date?->format('Y-m-d') ?? '',
            $inv->date_paid?->format('Y-m-d H:i:s') ?? '',
        ]);

        return $this->streamCsvDownload(
            'invoices-'.now()->format('Y-m-d').'.csv',
            ['ID', 'Invoice #', 'Client', 'Email', 'Status', 'Subtotal', 'Tax', 'Total', 'Payment Method', 'Date', 'Due Date', 'Date Paid'],
            $rows
        );
    }

    /**
     * Download invoice as PDF.
     */
    public function downloadPdf(Invoice $invoice, InvoicePdfService $pdfService)
    {
        return $pdfService->download($invoice);
    }
}
