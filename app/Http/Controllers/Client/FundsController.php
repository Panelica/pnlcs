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

        return view('client.funds.index', compact('gateways') + $this->rateContext());
    }

    /**
     * The rate and currencies used when adding funds.
     *
     * The customer pays in the billing currency and the balance is kept in
     * the shop currency - every price is in it, and keeping the balance in
     * the billing currency would mean one more conversion the other way on
     * every sale. Which bulletin the rate came from is shown to the customer;
     * they should not be asked to pay in without seeing how their money will
     * be converted.
     *
     * @return array<string, mixed>
     */
    private function rateContext(): array
    {
        $shop = \App\Models\Currency::getDefault();
        $code = (string) \App\Models\Setting::get('BillingCurrency', '');
        $billing = $code !== '' ? \App\Models\Currency::where('code', $code)->first() : null;

        // The shop already bills in its own currency: no conversion, the form is in the shop currency.
        $rate = ($billing && $shop && strtoupper($billing->code) !== strtoupper($shop->code) && (float) $billing->rate > 0)
            ? (float) $billing->rate
            : null;

        return [
            'shopCurrency'    => $shop,
            'billingCurrency' => $rate !== null ? $billing : null,
            'exchangeRate'    => $rate,
            'rateSource'      => \App\Models\Setting::get('ExchangeRateSource') ?: null,
            'rateDate'        => \App\Models\Setting::get('ExchangeRateDate') ?: null,
            'rateBulletin'    => \App\Models\Setting::get('ExchangeRateBulletin') ?: null,
        ];
    }

    public function store(Request $request)
    {
        $ctx = $this->rateContext();
        $rate = $ctx['exchangeRate'];

        // The entered amount is in the currency the customer pays in: the
        // billing currency if there is one, the shop currency otherwise. The
        // minimum has to be in that currency too - 5 of one is not 5 of the other.
        $minimum = $rate !== null ? funds_round_preset(5 * $rate) : 5;
        $maximum = $rate !== null ? funds_round_preset(10000 * $rate) : 10000;

        $validated = $request->validate([
            'amount'         => "required|numeric|min:{$minimum}|max:{$maximum}",
            'payment_method' => 'required|string|max:50',
        ]);

        $paidAmount = (float) $validated['amount'];

        // What reaches the invoice and the balance is always in the shop currency.
        $validated['amount'] = $rate !== null
            ? round($paidAmount / $rate, 2)
            : $paidAmount;

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

        // The description carries the rate that was applied: a customer
        // looking at the invoice six months later should be able to see why
        // their 1000 became 20.76.
        $description = __('messages.invoice.add_funds_description');

        if ($rate !== null) {
            $description .= ' — ' . __('client.funds.rate_line', [
                'paid'   => number_format($paidAmount, 2, ',', '.') . ' ' . ($ctx['billingCurrency']->suffix ?: $ctx['billingCurrency']->code),
                'rate'   => number_format($rate, 4, ',', '.'),
                'source' => trim(($ctx['rateSource'] ?? '') . ' ' . ($ctx['rateDate'] ?? '')),
            ]);
        }

        InvoiceItem::create([
            'invoice_id'  => $invoice->id,
            'client_id'   => $client->id,
            'type'        => 'AddFunds',
            'description' => $description,
            'amount'      => $validated['amount'],
            'taxed'       => false,
        ]);

        return redirect()->route('client.invoices.show', $invoice)
            ->with('success', __('messages.success.invoice_created_please_complete_payment_to_add_fun'));
    }
}
