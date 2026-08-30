<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesClient;
use App\Services\InvoiceService;
use App\Services\Module\ModuleRegistry;
use Illuminate\Http\Request;

class FundsController extends Controller
{
    use ResolvesClient;

    public function index()
    {
        // r118-funds: the same question the checkout and the invoice page ask -
        // switched on, and holding the keys it authenticates with. This page
        // used to list every gateway that had ever had a setting saved.
        $gateways = collect(app(ModuleRegistry::class)->usableGateways())->sort()->values();

        $client = $this->currentClient();
        $taxData = $client ? app(InvoiceService::class)->calculateTax(1, $client->id) : ['tax_rate' => 0.0];
        $taxRate = (float) ($taxData['tax_rate'] ?? 0.0);

        $currency = \App\Models\Currency::getDefault();

        return view('client.funds.index', [
            'gateways'       => $gateways,
            'taxRate'        => $taxRate,
            'currencyPrefix' => $currency->prefix ?? '',
            'currencySuffix' => $currency->suffix ?? '',
        ]);
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

        // The Add Funds line is taxable: the customer pays amount + VAT, while
        // their balance is credited with the net amount once the invoice is paid.
        $invoice = app(InvoiceService::class)->createInvoice($client, [
            [
                'type'        => 'AddFunds',
                'description' => __('messages.invoice.add_funds_description'),
                'amount'      => $validated['amount'],
                'taxed'       => true,
            ],
        ], [
            'date'           => today(),
            'due_date'       => today(),
            'payment_method' => $gateway,
        ]);

        return redirect()->route('client.invoices.show', $invoice)
            ->with('success', __('messages.success.invoice_created_please_complete_payment_to_add_fun'));
    }
}
