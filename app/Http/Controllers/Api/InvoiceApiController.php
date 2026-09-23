<?php

namespace App\Http\Controllers\Api;

use App\Models\BillableItem;
use App\Models\Client;
use App\Models\Currency;
use App\Enums\InvoiceStatus;
use Illuminate\Validation\Rule;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Services\InvoiceGenerationService;
use App\Services\InvoiceService;
use App\Services\PaymentService;
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
        $this->alias($request, 'duedate', 'due_date');
        $this->alias($request, 'paymentmethod', 'payment_method');
        // Collecting the money runs entirely off these two. The overdue run
        // marks unpaid invoices, the late fee and the suspension act on overdue
        // ones, the reminders go out for unpaid and overdue, and the client area
        // lists what is owed - all by matching the status string, which is not
        // cast to the enum. One outside the nine the panel knows left the
        // invoice in none of those: the customer owed the money and was never
        // asked for it again. The due date is the clock all of it runs on and
        // was taking any string at all.
        $request->validate([
            'status' => ['sometimes', Rule::enum(InvoiceStatus::class)],
            'due_date' => ['sometimes', 'date'],
            'date' => ['sometimes', 'date'],
            // A gateway this installation has; an unknown name is a method
            // nothing can take the payment through.
            'payment_method' => ['sometimes', 'nullable', 'string', function ($attribute, $value, $fail) {
                if ($value !== null && $value !== '' && ! app(\App\Services\Module\ModuleRegistry::class)->getGatewayModule((string) $value)) {
                    $fail('The payment method is not a payment gateway installed here.');
                }
            }],
        ]);

        foreach (['date', 'due_date', 'payment_method', 'notes'] as $f) {
            if ($request->has($f)) {
                $invoice->$f = $request->$f;
            }
        }
        $invoice->save();

        // A status is a state the rest of the system acts on, not a label.
        // Writing "paid" onto the row skipped the payment chain - no
        // transaction, no InvoicePaid, nothing provisioned - and "cancelled"
        // skipped handing back what had been paid. Both go through the same
        // doors the panel uses; the remaining statuses are plain bookkeeping.
        if ($request->has('status')) {
            $status = strtolower((string) $request->status);
            $service = app(InvoiceService::class);

            if ($status === InvoiceStatus::Paid->value) {
                $service->markPaid($invoice, $request->input('transid'), (string) ($request->input('gateway') ?: 'manual'));
            } elseif ($status === InvoiceStatus::Cancelled->value) {
                $service->cancelInvoice($invoice);
            } else {
                $invoice->update(['status' => $status]);
            }
        }

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
    /**
     * Apply existing client credit to an invoice. This used to be an alias of
     * addcredit - an operation that moves money in the OPPOSITE direction -
     * so a call shaped like the reference screen described (clientid,
     * invoiceid, amount) increased the client's balance and left the invoice
     * unpaid.
     */
    public function applyCredit(Request $request, InvoiceService $invoices)
    {
        $invoice = Invoice::find($request->invoiceid);
        if (! $invoice) {
            return $this->error('Invoice Not Found', 404);
        }

        $validated = $request->validate(['amount' => 'required|numeric|min:0.01']);

        // clientid is optional, but when given it must be the invoice's owner
        // - silently spending some OTHER client's balance is how books stop
        // adding up.
        if ($request->filled('clientid') && (int) $request->clientid !== (int) $invoice->client_id) {
            return $this->error('Client ID does not match the invoice', 400);
        }

        $amount = (float) $validated['amount'];
        if ($amount > (float) $invoice->client->credit) {
            return $this->error('Amount exceeds the client credit balance', 400);
        }

        $invoice = $invoices->applyCredit($invoice, $amount);

        return $this->success([
            'invoiceid' => $invoice->id,
            'amount' => $amount,
            'remaining_credit' => (float) $invoice->client->fresh()->credit,
        ]);
    }

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
        // A transaction pointing at an invoice that does not exist, or at
        // somebody else's invoice, is a ledger line nobody can reconcile.
        $validated = $request->validate([
            'userid' => 'required|exists:clients,id',
            'description' => 'required|string',
            'amountin' => 'nullable|numeric|min:0',
            'amountout' => 'nullable|numeric|min:0',
            'invoiceid' => ['nullable', 'integer', Rule::exists('invoices', 'id')->where('client_id', $request->input('userid'))],
            'transid' => 'nullable|string|max:255',
            'gateway' => 'nullable|string|max:100',
        ]);
        $tx = Transaction::create(['client_id' => $validated['userid'], 'date' => now()->format('Y-m-d'), 'description' => $validated['description'], 'amount_in' => $validated['amountin'] ?? 0, 'amount_out' => $validated['amountout'] ?? 0, 'transaction_id' => $request->transid, 'invoice_id' => $request->invoiceid, 'gateway' => $request->gateway]);

        return $this->success(['transactionid' => $tx->id]);
    }

    public function getTransactions(Request $request)
    {
        $query = Transaction::with('client');
        // WHMCS names this filter clientid; only userid was read, so a caller
        // asking for one customer's payments got everyone's.
        $clientId = $request->input('clientid', $request->input('userid'));
        if (filled($clientId)) {
            $query->where('client_id', $clientId);
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
        $request->validate(['amount' => 'nullable|numeric|min:0']);
        if ($request->has('description')) {
            $tx->description = $request->description;
        }
        // The row keeps money in and money out; "amount" is the WHMCS name
        // for what came in. Assigning a column that does not exist answered
        // with a database error.
        if ($request->has('amount')) {
            $tx->amount_in = (float) $request->amount;
        }
        $tx->save();

        return $this->success(['transactionid' => $tx->id]);
    }

    public function genInvoices(Request $request)
    {
        // The run bills every due service in the installation. A caller naming
        // one customer or one service expected that one only, and got the whole
        // run instead - invoices for everybody, raised by a request about one.
        if ($request->filled('clientid') || $request->filled('serviceids') || $request->filled('domainids') || $request->filled('addonids')) {
            return $this->error('geninvoices runs for every due service; filtering by client, service, domain or addon is not supported. Call it without filters, or create the invoice with createinvoice.', 422);
        }

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
        $validated = $request->validate(['clientid' => 'required|exists:clients,id', 'description' => 'required|string|max:255', 'amount' => 'required|numeric', 'duedate' => 'nullable|date']);
        $item = BillableItem::create(['client_id' => $validated['clientid'], 'description' => $validated['description'], 'amount' => $validated['amount'], 'due_date' => $request->duedate]);

        return $this->success(['billableitemid' => $item->id]);
    }

    public function getPayMethods(Request $request)
    {
        $client = Client::find($request->clientid);
        if (! $client) {
            return $this->error('Client Not Found', 404);
        }

        // The customer's stored methods, as the client area lists them. This
        // answered an empty list for every customer; cards have been stored
        // since automatic payment arrived. Tokens and gateway customer ids
        // stay out - they are what a charge is made with.
        $methods = PaymentMethod::where('client_id', $client->id)
            ->whereNull('detach_requested_at')
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get()
            ->map(fn (PaymentMethod $m) => [
                'id' => $m->id,
                'type' => $m->payment_type,
                'description' => $m->description,
                'gateway_name' => $m->gateway_name,
                'card_type' => $m->card_brand,
                'card_last_four' => $m->last_four,
                'expiry_date' => $m->exp_month && $m->exp_year ? sprintf('%02d/%d', $m->exp_month, $m->exp_year) : $m->expiry_date,
                'is_default' => (bool) $m->is_default,
                'status' => $m->status,
            ])
            ->values();

        return $this->success(['clientid' => $client->id, 'paymethods' => $methods->all()]);
    }

    /**
     * A card is stored through the gateway's own form, with the customer
     * there to pass 3-D Secure; there is nothing an API caller could send
     * that would store one. The client area's "add card" screen is the way.
     */
    public function addPayMethod(Request $request)
    {
        return $this->error('Adding a payment method needs the customer at the gateway\'s own card form (3-D Secure). Ask them to add it from the client area.', 501);
    }

    /**
     * Make a stored method the default, or rename it. The same model method
     * the client area's "make default" button uses.
     */
    public function updatePayMethod(Request $request)
    {
        $request->validate([
            'clientid' => 'required|integer',
            'paymethodid' => 'required|integer',
            'set_as_default' => 'sometimes|boolean',
            'description' => 'sometimes|nullable|string|max:255',
        ]);

        $method = PaymentMethod::where('client_id', $request->clientid)->find($request->paymethodid);
        if (! $method) {
            return $this->error('Payment method not found for that client.', 404);
        }

        if ($request->has('description')) {
            $method->update(['description' => $request->description]);
        }
        if ($request->boolean('set_as_default')) {
            $method->makeDefault();
        }

        return $this->success(['paymethodid' => $method->id, 'is_default' => (bool) $method->fresh()->is_default]);
    }

    /**
     * Remove a stored method: PNLCS stops using it at once, and the gateway is
     * asked to forget it on the next detach run - what the client area does.
     */
    public function deletePayMethod(Request $request)
    {
        $request->validate(['clientid' => 'required|integer', 'paymethodid' => 'required|integer']);

        $method = PaymentMethod::where('client_id', $request->clientid)->find($request->paymethodid);
        if (! $method) {
            return $this->error('Payment method not found for that client.', 404);
        }

        $method->remove();

        return $this->success(['paymethodid' => (int) $request->paymethodid]);
    }
}
