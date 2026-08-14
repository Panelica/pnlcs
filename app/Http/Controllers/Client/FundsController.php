<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesClient;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\InvoiceService;
use App\Services\Module\ModuleRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FundsController extends Controller
{
    use ResolvesClient;

    public function index()
    {
        // r118-funds: the same question the checkout and the invoice page ask -
        // switched on, and holding the keys it authenticates with. This page
        // used to list every gateway that had ever had a setting saved.
        $gateways = collect(app(ModuleRegistry::class)->usableGateways())->sort()->values();

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

        // A row in the settings table only means somebody opened the form once.
        // Taking money through a gateway on that basis leaves the customer at a
        // payment page that cannot charge them.
        $gateway = $validated['payment_method'];

        if (! in_array($gateway, app(ModuleRegistry::class)->usableGateways(), true)) {
            return back()->with('error', __('messages.error.gateway_not_configured', ['gateway' => ucfirst($gateway)]));
        }

        $invoice = Invoice::create([
            'client_id'      => $client->id,
            // Freeze the buyer alongside the money (issue #7)
            ...Invoice::buyerSnapshotFrom($client),
            'invoice_num'    => app(InvoiceService::class)->generateInvoiceNumber(),
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
