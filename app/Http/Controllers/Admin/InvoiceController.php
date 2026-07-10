<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Traits\CsvExportable;
use App\Models\Client;
use App\Models\Invoice;
use App\Enums\InvoiceStatus;
use App\Models\PaymentMethod;
use App\Services\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Services\InvoicePdfService;

class InvoiceController extends Controller
{
    use CsvExportable;

    public function __construct(
        protected InvoiceService $invoiceService
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
                  ->orWhereHas('client', fn ($c) =>
                      $c->where('first_name', 'like', "%{$request->search}%")
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
     * Show the invoice creation form.
     */
    public function create(Request $request): View
    {
        $clients        = Client::orderBy('first_name')->get();
        $paymentMethods = PaymentMethod::orderBy('description')->get();

        $selectedClient = $request->filled('client_id')
            ? Client::find($request->client_id)
            : null;

        return view('admin.invoices.create', compact('clients', 'paymentMethods', 'selectedClient'));
    }

    /**
     * Store a new invoice created from the admin form.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'client_id'       => ['required', 'exists:clients,id'],
            'date'            => ['required', 'date'],
            'due_date'        => ['required', 'date'],
            'payment_method'  => ['nullable', 'string', 'max:100'],
            'notes'           => ['nullable', 'string', 'max:2000'],
            'items'           => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.amount'      => ['required', 'numeric', 'min:0'],
            'items.*.taxed'       => ['nullable', 'boolean'],
        ]);

        $client = Client::findOrFail($validated['client_id']);

        $items = array_map(fn ($item) => [
            'type'        => 'Other',
            'description' => $item['description'],
            'amount'      => (float) $item['amount'],
            'taxed'       => isset($item['taxed']) ? (bool) $item['taxed'] : true,
        ], $validated['items']);

        $invoice = $this->invoiceService->createInvoice($client, $items, [
            'date'           => $validated['date'],
            'due_date'       => $validated['due_date'],
            'payment_method' => $validated['payment_method'] ?? null,
            'notes'          => $validated['notes'] ?? null,
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
            'gateway'        => ['nullable', 'string', 'max:100'],
        ]);

        $this->invoiceService->markPaid(
            $invoice,
            $validated['transaction_id'] ?? null,
            $validated['gateway'] ?? 'manual'
        );

        return back()->with('success', __('admin.messages.invoice_marked_paid', ['num' => $invoice->invoice_num]));
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
            'invoices-' . now()->format('Y-m-d') . '.csv',
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