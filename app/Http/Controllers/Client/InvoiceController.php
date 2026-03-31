<?php
namespace App\Http\Controllers\Client;
use App\Http\Controllers\Controller;
use App\Models\Invoice;

class InvoiceController extends Controller
{
    public function index() {
        $invoices = Invoice::with("items")->where("client_id", $this->getClientId())->orderBy("id","desc")->paginate(25);
        return view("client.invoices.index", compact("invoices"));
    }
    public function show(Invoice $invoice) {
        abort_if($invoice->client_id !== $this->getClientId(), 403);
        $invoice->load("items");
        return view("client.invoices.show", compact("invoice"));
    }
    private function getClientId() { return auth()->user()->clients()->first()?->id ?? 0; }
}
