<?php

namespace App\Http\Controllers\Api;

use App\Models\BillableItem;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Services\InvoiceGenerationService;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InvoiceApiController extends BaseApiController
{
    public function getInvoices(Request $request)
    {
        $query = Invoice::with('client');
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('userid')) {
            $query->where('client_id', $request->userid);
        }
        $invoices = $query->orderBy('id', 'desc')->paginate($this->getPerPage(), ['*'], 'page', $this->getPage());

        return $this->paginated($invoices);
    }

    public function getInvoice(Request $request)
    {
        $invoice = Invoice::with('client', 'items')->find($request->invoiceid);
        if (! $invoice) {
            return $this->error('Invoice Not Found', 404);
        }

        return $this->success(['invoice' => $invoice->toArray()]);
    }

    public function createInvoice(Request $request)
    {
        $validated = $request->validate([
            'userid' => 'required|exists:clients,id',
            'date' => 'nullable|date',
            'duedate' => 'nullable|date',
            'paymentmethod' => 'nullable|string',
            'status' => 'nullable|in:draft,unpaid,paid',
            'notes' => 'nullable|string',
            'items' => 'nullable|array',
            'items.*.description' => 'required_with:items|string|max:255',
            'items.*.amount' => 'required_with:items|numeric',
            'items.*.taxed' => 'nullable|boolean',
        ]);

        $items = $this->lineItemsFrom($request, $validated);

        if ($items === []) {
            return $this->error('An invoice needs at least one line: send items[] or itemdescription1 with itemamount1.', 422);
        }

        $client = Client::findOrFail($validated['userid']);

        // Through the invoice service, so the totals, the tax, the customer's
        // group discount and the created event happen as they do everywhere
        // else. This endpoint used to write an empty invoice on its own.
        $invoice = app(InvoiceService::class)->createInvoice($client, $items, array_filter([
            'date' => $validated['date'] ?? null,
            'due_date' => $validated['duedate'] ?? null,
            'payment_method' => $validated['paymentmethod'] ?? null,
            'status' => $validated['status'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]));

        return $this->success(['invoiceid' => $invoice->id, 'total' => (float) $invoice->total]);
    }

    /**
     * The lines, however they were sent: items[] or WHMCS-style numbered fields.
     *
     * @return array<int, array<string, mixed>>
     */
    private function lineItemsFrom(Request $request, array $validated): array
    {
        $items = [];

        foreach ($validated['items'] ?? [] as $item) {
            $items[] = [
                'type' => 'Other',
                'rel_id' => 0,
                'description' => $item['description'],
                'amount' => (float) $item['amount'],
                'taxed' => (bool) ($item['taxed'] ?? true),
            ];
        }

        for ($i = 1; $i <= 50; $i++) {
            $description = $request->input("itemdescription{$i}");

            if ($description === null || $description === '') {
                continue;
            }

            $amount = $request->input("itemamount{$i}");

            if (! is_numeric($amount)) {
                throw ValidationException::withMessages([
                    "itemamount{$i}" => "itemamount{$i} is required and must be a number.",
                ]);
            }

            $items[] = [
                'type' => 'Other',
                'rel_id' => 0,
                'description' => (string) $description,
                'amount' => (float) $amount,
                'taxed' => (bool) $request->input("itemtaxed{$i}", true),
            ];
        }

        return $items;
    }

    public function updateInvoice(Request $request)
    {
        $invoice = Invoice::find($request->invoiceid);
        if (! $invoice) {
            return $this->error('Invoice Not Found', 404);
        }
        foreach (['status', 'due_date', 'payment_method', 'notes'] as $f) {
            if ($request->has($f)) {
                $invoice->$f = $request->$f;
            }
        }
        $invoice->save();

        return $this->success(['invoiceid' => $invoice->id]);
    }

    /**
     * Record a payment against an invoice.
     *
     * This used to write the transaction and flip the status by hand, which
     * skipped everything a payment is supposed to set off: the same reference
     * could be banked twice, a part payment was not recognised as one, an
     * overpayment disappeared instead of becoming credit, and nothing waiting
     * on a paid invoice - a suspended service, an order still to be
     * provisioned, an upgrade to apply - was ever told. PaymentService is the
     * one place that does all of it, and every other way of taking money
     * already goes through it.
     */
    public function addInvoicePayment(Request $request, PaymentService $payments)
    {
        $invoice = Invoice::find($request->invoiceid);
        if (! $invoice) {
            return $this->error('Invoice Not Found', 404);
        }
        $validated = $request->validate(['transid' => 'required|string', 'amount' => 'required|numeric|min:0.01', 'gateway' => 'nullable|string']);

        $result = $payments->applyPayment(
            $invoice,
            $validated['gateway'] ?? 'banktransfer',
            $validated['transid'],
            (float) $validated['amount'],
        );

        if (! ($result['success'] ?? false)) {
            return $this->error($result['message'] ?? 'Payment could not be recorded', 422);
        }

        $transaction = Transaction::where('transaction_id', $validated['transid'])
            ->where('invoice_id', $invoice->id)
            ->latest('id')
            ->first();

        return $this->success([
            'transactionid' => $transaction?->id,
            'status' => $result['status'] ?? $invoice->fresh()->status,
            'balance' => $result['balance'] ?? null,
            'duplicate' => (bool) ($result['duplicate'] ?? false),
        ]);
    }

    public function addTransaction(Request $request)
    {
        $validated = $request->validate(['userid' => 'required|exists:clients,id', 'description' => 'required|string', 'amountin' => 'nullable|numeric', 'amountout' => 'nullable|numeric']);
        $tx = Transaction::create(['client_id' => $validated['userid'], 'date' => now()->format('Y-m-d'), 'description' => $validated['description'], 'amount_in' => $validated['amountin'] ?? 0, 'amount_out' => $validated['amountout'] ?? 0, 'transaction_id' => $request->transid, 'invoice_id' => $request->invoiceid, 'gateway' => $request->gateway]);

        return $this->success(['transactionid' => $tx->id]);
    }

    public function getTransactions(Request $request)
    {
        $query = Transaction::with('client');
        if ($request->filled('userid')) {
            $query->where('client_id', $request->userid);
        }
        if ($request->filled('invoiceid')) {
            $query->where('invoice_id', $request->invoiceid);
        }

        return $this->paginated($query->orderBy('id', 'desc')->paginate($this->getPerPage(), ['*'], 'page', $this->getPage()));
    }

    public function getCurrencies()
    {
        return $this->success(['currencies' => Currency::all()->toArray()]);
    }

    public function updateTransaction(Request $request)
    {
        $tx = Transaction::find($request->transactionid);
        if (! $tx) {
            return $this->error('Transaction Not Found', 404);
        }
        foreach (['description', 'amount'] as $f) {
            if ($request->has($f)) {
                $tx->$f = $request->$f;
            }
        }
        $tx->save();

        return $this->success(['transactionid' => $tx->id]);
    }

    public function genInvoices(Request $request)
    {
        $summary = app(InvoiceGenerationService::class)->generateDueInvoices();

        return $this->success([
            'generated' => $summary['generated'] ?? 0,
            'skipped' => $summary['skipped'] ?? 0,
            'errors' => $summary['errors'] ?? 0,
            'invoice_ids' => $summary['invoice_ids'] ?? [],
        ]);
    }

    public function capturePayment(Request $request)
    {
        // Charging a stored payment method needs a tokenising gateway and a
        // token to charge; neither exists here. Answering "captured" told the
        // caller money had been taken when the invoice was untouched.
        return $this->error('Capturing a stored payment method is not implemented. Take the payment from the client area.', 501);
    }

    public function addBillableItem(Request $request)
    {
        $validated = $request->validate(['clientid' => 'required|exists:clients,id', 'description' => 'required', 'amount' => 'required|numeric']);
        $item = BillableItem::create(['client_id' => $validated['clientid'], 'description' => $validated['description'], 'amount' => $validated['amount'], 'due_date' => $request->duedate]);

        return $this->success(['billableitemid' => $item->id]);
    }

    public function getPayMethods(Request $request)
    {
        $client = Client::find($request->clientid);
        if (! $client) {
            return $this->error('Client Not Found', 404);
        }

        return $this->success(['paymethods' => []]);
    }

    // Stored payment methods are not managed through this API. Saying "deleted"
    // to a caller who asked to remove a stored card, and removing nothing, is
    // the worst of both.
    public function addPayMethod(Request $request)
    {
        return $this->notImplemented('addpaymethod');
    }

    public function updatePayMethod(Request $request)
    {
        return $this->notImplemented('updatepaymethod');
    }

    public function deletePayMethod(Request $request)
    {
        return $this->notImplemented('deletepaymethod');
    }

    private function notImplemented(string $endpoint): JsonResponse
    {
        return $this->error("The {$endpoint} endpoint is not implemented. Manage payment methods from the client area.", 501);
    }
}
