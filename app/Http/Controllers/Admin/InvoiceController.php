<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $query = Invoice::with("client");
        if ($request->filled("status")) { $query->where("status", $request->status); }
        if ($request->filled("search")) {
            $query->where(function ($q) use ($request) {
                $q->where("invoice_num", "like", "%{$request->search}%")
                  ->orWhereHas("client", fn ($c) => $c->where("first_name", "like", "%{$request->search}%")->orWhere("last_name", "like", "%{$request->search}%"));
            });
        }
        $invoices = $query->orderBy("created_at", "desc")->paginate(25);
        return view("admin.invoices.index", compact("invoices"));
    }

    public function show(Invoice $invoice)
    {
        $invoice->load("client", "items", "transactions");
        return view("admin.invoices.show", compact("invoice"));
    }
}
