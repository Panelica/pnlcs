<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FundsController extends Controller
{
    public function index()
    {
        // Get distinct payment gateways configured in the system
        $gateways = DB::table('gateway_settings')
            ->select('gateway')
            ->distinct()
            ->orderBy('gateway')
            ->pluck('gateway');

        return view('client.funds.index', compact('gateways'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'amount'         => 'required|numeric|min:5|max:10000',
            'payment_method' => 'required|string|max:50',
        ]);

        $clientId = auth()->user()->clients()->first()?->id ?? 0;

        $invoice = Invoice::create([
            'client_id'      => $clientId,
            'date'           => today(),
            'due_date'       => today(),
            'subtotal'       => $validated['amount'],
            'credit'         => 0,
            'tax'            => 0,
            'tax2'           => 0,
            'total'          => $validated['amount'],
            'tax_rate'       => 0,
            'tax_rate2'      => 0,
            'status'         => 'Unpaid',
            'payment_method' => $validated['payment_method'],
        ]);

        InvoiceItem::create([
            'invoice_id'  => $invoice->id,
            'type'        => 'AddFunds',
            'description' => 'Add Funds to Account',
            'amount'      => $validated['amount'],
            'taxed'       => false,
        ]);

        return redirect()->route('client.invoices.show', $invoice)
            ->with('success', 'Invoice created. Please complete payment to add funds to your account.');
    }
}
