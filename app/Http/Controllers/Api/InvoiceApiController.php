<?php

namespace App\Http\Controllers\Api;

use App\Models\BillableItem;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Services\InvoiceGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        ]);
        $invoice = Invoice::create([
            'client_id' => $validated['userid'],
            'date' => $validated['date'] ?? now()->format('Y-m-d'),
            'due_date' => $validated['duedate'] ?? now()->addDays(7)->format('Y-m-d'),
            'payment_method' => $validated['paymentmethod'] ?? null,
            'status' => $validated['status'] ?? 'unpaid',
        ]);

        return $this->success(['invoiceid' => $invoice->id]);
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

    public function addInvoicePayment(Request $request)
    {
        $invoice = Invoice::find($request->invoiceid);
        if (! $invoice) {
            return $this->error('Invoice Not Found', 404);
        }
        $validated = $request->validate(['transid' => 'required|string', 'amount' => 'required|numeric', 'gateway' => 'nullable|string']);
        $tx = Transaction::create(['client_id' => $invoice->client_id, 'date' => now()->format('Y-m-d'), 'description' => "Invoice #{$invoice->id} Payment", 'amount_in' => $validated['amount'], 'transaction_id' => $validated['transid'], 'invoice_id' => $invoice->id, 'gateway' => $validated['gateway'] ?? null]);
        if ($validated['amount'] >= $invoice->total) {
            $invoice->update(['status' => 'paid', 'date_paid' => now()]);
        }

        return $this->success(['transactionid' => $tx->id]);
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
