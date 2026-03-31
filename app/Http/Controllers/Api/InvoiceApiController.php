<?php
namespace App\Http\Controllers\Api;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Http\Request;

class InvoiceApiController extends BaseApiController
{
    public function getInvoices(Request $request)
    {
        $query = Invoice::with("client");
        if ($request->filled("status")) { $query->where("status", $request->status); }
        if ($request->filled("userid")) { $query->where("client_id", $request->userid); }
        $invoices = $query->orderBy("id", "desc")->paginate($request->get("limitnum", 25));
        return $this->paginated($invoices);
    }

    public function getInvoice(Request $request)
    {
        $invoice = Invoice::with("client", "items")->find($request->invoiceid);
        if (!$invoice) return $this->error("Invoice Not Found", 404);
        return $this->success(["invoice" => $invoice->toArray()]);
    }

    public function createInvoice(Request $request)
    {
        $validated = $request->validate([
            "userid" => "required|exists:clients,id",
            "date" => "nullable|date",
            "duedate" => "nullable|date",
            "paymentmethod" => "nullable|string",
            "status" => "nullable|in:draft,unpaid,paid",
        ]);
        $invoice = Invoice::create([
            "client_id" => $validated["userid"],
            "date" => $validated["date"] ?? now()->format("Y-m-d"),
            "due_date" => $validated["duedate"] ?? now()->addDays(7)->format("Y-m-d"),
            "payment_method" => $validated["paymentmethod"] ?? null,
            "status" => $validated["status"] ?? "unpaid",
        ]);
        return $this->success(["invoiceid" => $invoice->id]);
    }

    public function updateInvoice(Request $request)
    {
        $invoice = Invoice::find($request->invoiceid);
        if (!$invoice) return $this->error("Invoice Not Found", 404);
        foreach (["status", "due_date", "payment_method", "notes"] as $f) { if ($request->has($f)) $invoice->$f = $request->$f; }
        $invoice->save();
        return $this->success(["invoiceid" => $invoice->id]);
    }

    public function addInvoicePayment(Request $request)
    {
        $invoice = Invoice::find($request->invoiceid);
        if (!$invoice) return $this->error("Invoice Not Found", 404);
        $validated = $request->validate(["transid" => "required|string", "amount" => "required|numeric", "gateway" => "nullable|string"]);
        $tx = \App\Models\Transaction::create(["client_id" => $invoice->client_id, "date" => now()->format("Y-m-d"), "description" => "Invoice #{$invoice->id} Payment", "amount_in" => $validated["amount"], "transaction_id" => $validated["transid"], "invoice_id" => $invoice->id, "gateway" => $validated["gateway"] ?? null]);
        if ($validated["amount"] >= $invoice->total) { $invoice->update(["status" => "paid", "date_paid" => now()]); }
        return $this->success(["transactionid" => $tx->id]);
    }

    public function addTransaction(Request $request)
    {
        $validated = $request->validate(["userid" => "required|exists:clients,id", "description" => "required|string", "amountin" => "nullable|numeric", "amountout" => "nullable|numeric"]);
        $tx = \App\Models\Transaction::create(["client_id" => $validated["userid"], "date" => now()->format("Y-m-d"), "description" => $validated["description"], "amount_in" => $validated["amountin"] ?? 0, "amount_out" => $validated["amountout"] ?? 0, "transaction_id" => $request->transid, "invoice_id" => $request->invoiceid, "gateway" => $request->gateway]);
        return $this->success(["transactionid" => $tx->id]);
    }

    public function getTransactions(Request $request)
    {
        $query = \App\Models\Transaction::with("client");
        if ($request->filled("userid")) $query->where("client_id", $request->userid);
        if ($request->filled("invoiceid")) $query->where("invoice_id", $request->invoiceid);
        return $this->paginated($query->orderBy("id", "desc")->paginate($request->get("limitnum", 25)));
    }

    public function getCurrencies()
    {
        return $this->success(["currencies" => \App\Models\Currency::all()->toArray()]);
    }
}
