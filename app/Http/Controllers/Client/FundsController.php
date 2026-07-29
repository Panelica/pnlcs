<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesClient;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FundsController extends Controller
{
    use ResolvesClient;

    public function index()
    {
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

        $client = $this->currentClient();

        if (!$client) {
            return back()->with('error', __('messages.error.no_client_account_found_please_contact_support'));
        }

        // Check if gateway is configured
        $gateway = $validated['payment_method'];
        $configured = DB::table('gateway_settings')->where('gateway', $gateway)->exists();

        if (!$configured && !in_array($gateway, ['banktransfer'])) {
            return back()->with('error', __('messages.error.gateway_not_configured', ['gateway' => ucfirst($gateway)]));
        }

        $invoice = Invoice::create([
            'client_id'      => $client->id,
            'invoice_num'    => 'INV-' . strtoupper(Str::random(8)),
            'date'           => today(),
            'due_date'       => today(),
            'subtotal'       => $validated['amount'],
            'credit'         => 0,
            'tax'            => 0,
            'tax2'           => 0,
            'total'          => $validated['amount'],
            'tax_rate'       => 0,
            'tax_rate2'      => 0,
            'status'         => 'unpaid',
            'payment_method' => $gateway,
        ]);

        InvoiceItem::create([
            'invoice_id'  => $invoice->id,
            'client_id'   => $client->id,
            'type'        => 'AddFunds',
            'description' => __('messages.invoice.add_funds_description'),
            'amount'      => $validated['amount'],
            'taxed'       => false,
        ]);

        return redirect()->route('client.invoices.show', $invoice)
            ->with('success', __('messages.success.invoice_created_please_complete_payment_to_add_fun'));
    }
}
