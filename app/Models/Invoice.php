<?php
namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Support\CustomerCurrency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Invoice extends Model {
    use HasFactory;

    protected $fillable = ['client_id', 'invoice_num', 'date', 'due_date', 'date_paid', 'subtotal', 'credit', 'tax', 'tax2', 'total', 'tax_rate', 'tax_rate2', 'status', 'type', 'source_invoice_id', 'reminder_stage', 'reminder_sent_at', 'payment_method', 'pay_method_id', 'notes',
        // Buyer as it stood when the invoice was issued (issue #7). The money
        // was already frozen; these keep the document itself immutable.
        'buyer_first_name', 'buyer_last_name', 'buyer_company_name', 'buyer_email',
        'buyer_address1', 'buyer_address2', 'buyer_city', 'buyer_state',
        'buyer_postcode', 'buyer_country', 'buyer_tax_id', 'buyer_tax_exempt',
        'buyer_custom_fields',
        // For an official e-invoice: a company buyer needs a tax office, a
        // private buyer is identified by national ID rather than tax number,
        // and which of the two applies is read from the customer type.
        'buyer_tax_office', 'buyer_national_id', 'buyer_client_type',
        // The shop prices in one currency and may bill in another, at the
        // rate that stood when the invoice was raised.
        'source_currency', 'billing_currency', 'exchange_rate',
        'exchange_rate_source', 'exchange_rate_kind', 'exchange_rate_date', 'exchange_rate_ref',
        // How it was taxed when issued (#305): null = on top, 'inclusive', 'none'.
        'tax_mode',
        // The language the invoice was issued in, frozen at issue time so a
        // customer who later switches language still reprints the same text.
        'language'];

    /**
     * Freeze the billing rate the moment an invoice comes into existence.
     *
     * Prices are kept in the shop currency, but the customer pays in the
     * billing currency. Stamping the rate here - rather than converting at
     * print time - means a document reprinted next year still shows the lira
     * figure the customer actually agreed to.
     */
    protected static function booted(): void
    {
        static::creating(fn (Invoice $invoice) => self::stampBillingRate($invoice));
    }

    /** The billing currency and rate as they stand now, onto an invoice that has none yet. */
    public static function stampBillingRate(Invoice $invoice): void
    {
        $shop = Currency::getDefault();

        // The currency the amounts on this row are denominated in. Without
        // it, switching the shop currency later reinterprets every past
        // invoice at the new sign.
        if ($invoice->source_currency === null && $shop) {
            $invoice->source_currency = strtoupper($shop->code);
        }

        if ($invoice->billing_currency !== null) {
            return;
        }

        // The customer's own currency comes first, when the operator lets
        // customers choose (CustomerCurrency) and this one did. The amounts
        // stay in the shop currency; the stamp is what the customer reads.
        // Only over shop-currency amounts: the rate is against the shop.
        $own = $invoice->client_id && $shop && strtoupper((string) $invoice->source_currency) === strtoupper($shop->code)
            ? CustomerCurrency::forClient(Client::find($invoice->client_id))
            : null;

        if ($own) {
            $invoice->billing_currency = strtoupper($own->code);
            $invoice->exchange_rate = CustomerCurrency::rateOf($own);

            // The provenance settings describe the official lira rate
            // (pnlcs:currency-update): they belong on a lira stamp only.
            if (strtoupper($own->code) === 'TRY') {
                $invoice->exchange_rate_source = Setting::get('ExchangeRateSource') ?: null;
                $invoice->exchange_rate_kind = Setting::get('ExchangeRateKind') ?: null;
                $invoice->exchange_rate_date = Setting::get('ExchangeRateDate') ?: null;
                $invoice->exchange_rate_ref = Setting::get('ExchangeRateBulletin') ?: null;
            }

            return;
        }

        $code = (string) Setting::get('BillingCurrency', '');

        if ($code === '') {
            return;                       // not configured: shop currency only
        }

        if (! $shop || strtoupper($shop->code) === strtoupper($code)) {
            return;                       // already billing in the shop currency
        }

        $target = Currency::where('code', $code)->first();

        if (! $target || (float) $target->rate <= 0) {
            return;
        }

        $invoice->billing_currency = strtoupper($code);
        $invoice->exchange_rate = (float) $target->rate;

        // Provenance travels with the rate: a bare number on an invoice is
        // not something a customer can verify. The settings describe the
        // official lira rate, so they belong on a lira stamp only.
        if (strtoupper($code) === 'TRY') {
            $invoice->exchange_rate_source = Setting::get('ExchangeRateSource') ?: null;
            $invoice->exchange_rate_kind = Setting::get('ExchangeRateKind') ?: null;
            $invoice->exchange_rate_date = Setting::get('ExchangeRateDate') ?: null;
            $invoice->exchange_rate_ref = Setting::get('ExchangeRateBulletin') ?: null;
        }
    }

    /**
     * Give an unpaid Add Funds invoice today's rate before it is paid.
     *
     * An ordinary invoice keeps the rate it was raised at: it is a debt for
     * something already sold. An Add Funds invoice sells the shop currency
     * itself, and it stays payable for as long as nobody pays it. Paid months
     * later at the rate of the day it was opened, it bought balance at an old
     * price; with a currency that keeps falling against the shop's, opening
     * one and waiting was a way to buy balance cheaply.
     *
     * Only before any money has arrived on it: a payment in progress was
     * started at the rate on the invoice and is checked against it.
     *
     * @return bool whether the rate changed
     */
    public function refreshAddFundsRate(): bool
    {
        if (! in_array(strtolower((string) $this->status), ['unpaid', 'overdue'], true)
            || empty($this->billing_currency)
            || ! $this->items()->where('type', 'AddFunds')->exists()
            || Transaction::where('invoice_id', $this->id)->exists()) {
            return false;
        }

        $now = new Invoice(['client_id' => $this->client_id, 'source_currency' => $this->source_currency]);
        self::stampBillingRate($now);

        if ($now->billing_currency !== $this->billing_currency || (float) $now->exchange_rate <= 0
            || abs((float) $now->exchange_rate - (float) $this->exchange_rate) < 0.000001) {
            return false;
        }

        $this->forceFill([
            'exchange_rate' => $now->exchange_rate,
            'exchange_rate_source' => $now->exchange_rate_source,
            'exchange_rate_kind' => $now->exchange_rate_kind,
            'exchange_rate_date' => $now->exchange_rate_date,
            'exchange_rate_ref' => $now->exchange_rate_ref,
        ])->save();

        // The line says which rate was applied; it now says the new one.
        foreach ($this->items()->where('type', 'AddFunds')->get() as $item) {
            $item->update(['description' => self::addFundsDescription(
                (float) $item->amount * (float) $now->exchange_rate,
                (string) $now->billing_currency,
                (float) $now->exchange_rate,
                trim(($now->exchange_rate_source ?? '').' '.($now->exchange_rate_date ?? '')),
            )]);
        }

        return true;
    }

    /** The Add Funds line, with the rate that was applied when there is one. */
    public static function addFundsDescription(?float $paid = null, ?string $currency = null, ?float $rate = null, string $source = ''): string
    {
        $description = __('messages.invoice.add_funds_description');

        if ($rate !== null && $paid !== null && $currency !== null) {
            $sign = Currency::where('code', $currency)->first();
            $description .= ' — '.__('client.funds.rate_line', [
                'paid' => number_format($paid, 2, ',', '.').' '.(($sign?->suffix ?: null) ?? $currency),
                'rate' => number_format($rate, 4, ',', '.'),
                'source' => $source,
            ]);
        }

        return $description;
    }

    /**
     * An amount in the currency this invoice is billed in.
     */
    public function inBillingCurrency(float|int|string|null $amount): float
    {
        return round((float) $amount * (float) ($this->exchange_rate ?: 1), 2);
    }

    /**
     * Invoices the customer still owes money on.
     *
     * Overdue and part-paid ones count: an invoice does not stop being
     * outstanding because it went past its due date.
     */
    public function scopeOutstanding($query)
    {
        return $query->whereIn('status', ['unpaid', 'overdue', 'partially_paid']);
    }

    /**
     * Hide proformas that have been settled: once paid, a proforma is replaced
     * by its VAT invoice. Gated on the "hide paid proformas" setting.
     */
    public function scopeExcludeSettledProformas($query)
    {
        if (Setting::get('HidePaidProformas', '1') !== '1') {
            return $query;
        }

        return $query->whereNot(function ($q) {
            $q->where('type', 'proforma')->where('status', InvoiceStatus::Paid->value);
        });
    }
    protected function casts(): array { return ['date' => 'date', 'due_date' => 'date', 'date_paid' => 'datetime', 'subtotal' => 'decimal:2', 'credit' => 'decimal:2', 'tax' => 'decimal:2', 'total' => 'decimal:2', 'buyer_custom_fields' => 'array']; }

    public function client() { return $this->belongsTo(Client::class); }
    /**
     * What is still owed on this invoice.
     *
     * r131-due: the invoice page shows this to the customer as the remaining
     * balance, and every pay-now path used to ask the gateway for the total
     * instead - so somebody who had paid half by bank transfer was shown 60
     * left and their card was charged 100.
     */
    public function amountDue(): float
    {
        return max(0.0, app(\App\Services\PaymentService::class)->balance($this));
    }

    public function items() { return $this->hasMany(InvoiceItem::class); }

    /**
     * Is this document a proforma rather than a VAT invoice?
     *
     * The column defaults to 'vat', and an invoice raised before the proforma
     * scheme existed carries nothing at all, so the absence is read as a VAT
     * invoice. The two must not be titled the same: a proforma is a request
     * for payment, and in the countries that have the scheme a document headed
     * "Invoice" is a tax document whether it meant to be one or not.
     */
    public function isProforma(): bool
    {
        return ($this->type ?? 'vat') === 'proforma';
    }

    public const TAX_INCLUSIVE = 'inclusive';
    public const TAX_NONE = 'none';

    /** The tax mode for an invoice issued now, from the shop's tax settings. */
    public static function currentTaxMode(): ?string
    {
        return ! TaxRule::enabled() ? self::TAX_NONE : (TaxRule::pricesIncludeTax() ? self::TAX_INCLUSIVE : null);
    }

    /**
     * One line's rate, net and VAT, unrounded, as this invoice was taxed: the
     * line's own rate (the admin builder writes one), else the invoice rate on
     * a taxed line; none at all on an invoice issued with tax off; with prices
     * that include tax the line amount is gross and the VAT the part it holds.
     *
     * @return array{rate: float, net: float, vat: float}
     */
    public function lineTax(InvoiceItem $item): array
    {
        $amount = (float) $item->amount * max(1, (int) ($item->qty ?? 1));
        $rate = $this->tax_mode === self::TAX_NONE ? 0.0
            : ($item->tax_rate !== null ? (float) $item->tax_rate : ($item->taxed ? (float) $this->tax_rate : 0.0));
        $vat = TaxRule::taxShare($amount, $rate, $this->tax_mode === self::TAX_INCLUSIVE);

        return ['rate' => $rate, 'net' => $this->tax_mode === self::TAX_INCLUSIVE ? $amount - $vat : $amount, 'vat' => $vat];
    }

    /**
     * The VAT rows the invoice documents print: per tax rule (a line linked to
     * a rule groups by that rule, whatever its label says; a legacy line groups
     * by its label/rate), net and VAT summed and rounded once, highest rate
     * first. One place for the PDF, the client and admin invoice pages and
     * KSeF.
     *
     * @return array<string, array{rate: float, net: float, amount: float}>
     */
    public function vatBreakdown(): array
    {
        // Every line reads its rule below; load the relation once so the PDF,
        // the invoice pages and KSeF do not run a query per line.
        $this->items->loadMissing('taxRule');

        $groups = [];
        foreach ($this->items as $item) {
            $line = $this->lineTax($item);
            $rate = $line['rate'];

            if ($item->tax_rule_id !== null) {
                $key = 'rule:'.$item->tax_rule_id;
                // The line's own label is the text the document was issued with;
                // the rule's name is only the fallback for a line that has none,
                // so a rule renamed later does not rewrite a document already out.
                $label = $item->tax_label
                    ?: ($item->taxRule?->name ?: ($rate > 0 ? rtrim(rtrim(number_format($rate, 2), '0'), '.').'%' : null));
            } else {
                $label = $item->tax_label ?: ($rate > 0 ? rtrim(rtrim(number_format($rate, 2), '0'), '.').'%' : null);
                $key = $label;
            }

            if ($label === null || $label === '') {
                continue;
            }

            $groups[$key] ??= ['amount' => 0.0, 'rate' => $rate, 'net' => 0.0, 'label' => $label];
            $groups[$key]['amount'] += $line['vat'];
            $groups[$key]['net'] += $line['net'];
        }

        // The returned array is keyed by label for the consumers. Two groups can
        // share a label (two rules named the same), so sum them into one row
        // instead of letting the second overwrite the first and drop its VAT.
        $result = [];
        foreach ($groups as $group) {
            $result[$group['label']] ??= ['rate' => $group['rate'], 'net' => 0.0, 'amount' => 0.0];
            $result[$group['label']]['net'] += $group['net'];
            $result[$group['label']]['amount'] += $group['amount'];
        }
        foreach ($result as &$row) {
            $row['amount'] = round($row['amount'], 2);
            $row['net'] = round($row['net'], 2);
        }
        unset($row);

        uasort($result, fn ($a, $b) => $b['rate'] <=> $a['rate']);

        return $result;
    }
    public function transactions() { return $this->hasMany(Transaction::class); }

    /**
     * Where this invoice stands with the card that is meant to pay it.
     *
     * hasOne, because invoice_charge_attempts.invoice_id is unique - one
     * arbitrator per invoice, for all time. Null on every invoice in a shop
     * that does not collect by card.
     */
    public function chargeAttempt() { return $this->hasOne(InvoiceChargeAttempt::class); }

    /** The proforma this VAT invoice was issued from. */
    public function sourceInvoice() { return $this->belongsTo(self::class, 'source_invoice_id'); }

    /** The KSeF submission this invoice has, once it has been handed over. */
    public function ksefInvoice() { return $this->hasOne(KsefInvoice::class); }

    public function scopeUnpaid($q) { return $q->where('status', InvoiceStatus::Unpaid->value); }
    public function scopeOverdue($q) { return $q->where('status', InvoiceStatus::Overdue->value); }

    /**
     * The debts a client can be suspended over.
     *
     * One question, asked from one place: auto-suspend acts on these, and
     * unsuspend-on-payment leaves a service alone while one of them stands.
     * When the two asked it differently, a client behind on something that was
     * not the service - a domain renewal, a one-off charge - had that service
     * suspended each morning and switched back on half an hour later, with an
     * email each way.
     */
    public function scopeOverduePastGrace($q, int $graceDays)
    {
        return $q->where('status', InvoiceStatus::Overdue->value)
            ->where('due_date', '<', now()->subDays($graceDays));
    }
    public function scopePaid($q) { return $q->where('status', InvoiceStatus::Paid->value); }

    /**
     * The buyer fields to copy off a client when an invoice is issued.
     *
     * @return array<string,mixed>
     */
    public static function buyerSnapshotFrom(Client $client): array
    {
        return [
            'buyer_first_name' => $client->first_name,
            'buyer_last_name' => $client->last_name,
            'buyer_company_name' => $client->company_name,
            'buyer_email' => $client->email,
            'buyer_address1' => $client->address1,
            'buyer_address2' => $client->address2,
            'buyer_city' => $client->city,
            'buyer_state' => $client->state,
            'buyer_postcode' => $client->postcode,
            'buyer_country' => $client->country,
            'buyer_tax_id' => $client->tax_id,
            'buyer_tax_office' => $client->tax_office,
            'buyer_national_id' => $client->national_id,
            'buyer_client_type' => $client->client_type,
            'buyer_tax_exempt' => (bool) $client->tax_exempt,
            'buyer_custom_fields' => CustomField::invoiceSnapshot($client),
            'language' => $client->language ?: (string) \App\Models\Setting::get('DefaultLanguage', config('app.locale', 'en')),
        ];
    }

    /**
     * Buyer field as it should be shown on this invoice: the snapshot taken at
     * issue time, falling back to the live client record for invoices issued
     * before snapshots existed. Nothing was backfilled, so those keep rendering
     * exactly as they do today.
     */
    public function buyer(string $field): mixed
    {
        $snapshot = $this->getAttribute('buyer_' . $field);
        if ($snapshot !== null && $snapshot !== '') {
            return $snapshot;
        }

        return $this->client?->{$field};
    }

    /**
     * The buyer's custom fields as shown on this invoice: the snapshot taken
     * at issue time, lying flat in the same shape the snapshot builder returns
     * (field name => value). Invoices issued before custom-field snapshots
     * existed fall back to the live client's values, exactly like buyer() does.
     *
     * @return array<string, mixed>
     */
    public function buyerCustomFields(): array
    {
        $snapshot = $this->getAttribute('buyer_custom_fields');

        if (is_array($snapshot) && $snapshot !== []) {
            return $snapshot;
        }

        return CustomField::invoiceSnapshot($this->client);
    }

}
