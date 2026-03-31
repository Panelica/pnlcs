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
}
