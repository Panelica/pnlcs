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
        'language',
        // The seller as it stood when the invoice was issued: company details,
        // logo and the bank accounts printed on the document. Without these a
        // later settings change rewrote every past invoice.
        'seller_name', 'seller_legal_name', 'seller_website', 'seller_address',
        'seller_city', 'seller_postcode', 'seller_country', 'seller_phone',
        'seller_email', 'seller_tax_office', 'seller_tax_id', 'seller_mersis',
        'seller_logo', 'seller_bank_accounts', 'seller_snapshot_at'];

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

            // The provenance settings describe one official rate
            // (pnlcs:currency-update): they belong on a stamp in that
            // currency only.
            if (strtoupper($own->code) === self::officialRateCurrency()) {
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
        // official rate of one currency, so they belong on a stamp in that
        // currency only.
        if (strtoupper($code) === self::officialRateCurrency()) {
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
     * The currency the stored provenance (ExchangeRateSource and friends)
     * describes. Installations that last fetched before it was recorded only
     * ever had the TCMB lira rate.
     */
    private static function officialRateCurrency(): string
    {
        return strtoupper((string) (Setting::get('ExchangeRateCurrency') ?: 'TRY'));
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
     *
     * Hidden only when that replacement actually exists. A top-up is a payment
     * on account and issues no VAT invoice, so hiding it would make a paid
     * top-up disappear from every list with nothing standing in its place.
     */
    public function scopeExcludeSettledProformas($query)
    {
        if (Setting::get('HidePaidProformas', '1') !== '1') {
            return $query;
        }

        return $query->where(function ($q) {
            $q->where('type', '!=', 'proforma')
                ->orWhere('status', '!=', InvoiceStatus::Paid->value)
                ->orWhereDoesntHave('replacementInvoice');
        });
    }
    protected function casts(): array { return ['date' => 'date', 'due_date' => 'date', 'date_paid' => 'datetime', 'subtotal' => 'decimal:2', 'credit' => 'decimal:2', 'tax' => 'decimal:2', 'total' => 'decimal:2', 'buyer_custom_fields' => 'array', 'seller_bank_accounts' => 'array', 'seller_snapshot_at' => 'datetime']; }

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

    /**
     * The payment method a document shows when its own column is empty.
     *
     * Invoices issued before the field was filled, and generated proformas
     * that predate the creation-time default, still name how the customer
     * expects to pay. The stored value always wins; this only fills the gap
     * for the reader, it never rewrites the document.
     */
    public function displayPaymentMethod(): string
    {
        $method = trim((string) $this->payment_method);

        if ($method !== '' && strtolower($method) !== 'none') {
            return $method;
        }

        return $this->client?->billingPaymentMethod() ?? 'banktransfer';
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

    /** The VAT invoice issued from this proforma, once it has been paid. */
    public function replacementInvoice() { return $this->hasOne(self::class, 'source_invoice_id'); }

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

    /**
     * The seller fields to freeze on an invoice when it is issued.
     *
     * The company block, the logo and the bank accounts are the other half of
     * the document: a settings change years later must not alter a VAT invoice
     * that was already filed. The logo is copied, not referenced, so replacing
     * the shop logo does not rewrite the past.
     *
     * @param  array<string, mixed>  $company  InvoicePdfService::companyDetails()
     * @param  array<int, array<string, string>>  $accounts
     * @return array<string, mixed>
     */
    public static function sellerSnapshotFrom(array $company, array $accounts): array
    {
        return [
            'seller_name' => $company['name'] ?? null,
            'seller_legal_name' => $company['legal_name'] ?? null,
            'seller_website' => $company['domain'] ?? null,
            'seller_address' => $company['address'] ?? null,
            'seller_city' => $company['city'] ?? null,
            'seller_postcode' => $company['postcode'] ?? null,
            'seller_country' => $company['country'] ?? null,
            'seller_phone' => $company['phone'] ?? null,
            'seller_email' => $company['email'] ?? null,
            'seller_tax_office' => $company['tax_office'] ?? null,
            'seller_tax_id' => $company['tax_id'] ?? null,
            'seller_mersis' => $company['mersis'] ?? null,
            'seller_logo' => self::copySellerLogo($company['logo'] ?? null),
            'seller_bank_accounts' => array_values($accounts),
            'seller_snapshot_at' => now(),
        ];
    }

    /** Copy the logo into the invoice's own storage; the path is relative to storage/app. */
    private static function copySellerLogo(?string $source): ?string
    {
        if ($source === null || $source === '' || ! is_file($source)) {
            return null;
        }

        $hash = @sha1_file($source);
        if ($hash === false) {
            return null;
        }

        $extension = strtolower((string) pathinfo($source, PATHINFO_EXTENSION)) ?: 'png';
        $relative = 'invoices/seller-logos/'.$hash.'.'.$extension;
        $target = storage_path('app/'.$relative);

        if (! is_file($target)) {
            $directory = dirname($target);
            if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
                return null;
            }
            if (! @copy($source, $target)) {
                return null;
            }
        }

        return $relative;
    }

    /**
     * A seller field as shown on this invoice: the frozen snapshot, falling
     * back to the live settings for invoices issued before snapshots existed.
     */
    public function seller(string $field): mixed
    {
        if ($this->seller_snapshot_at !== null) {
            return $this->getAttribute('seller_'.$field);
        }

        $live = app(\App\Services\InvoicePdfService::class)->companyDetails();
        $map = ['website' => 'domain'];

        return $live[$map[$field] ?? $field] ?? null;
    }

    /**
     * The seller block the invoice renderer expects, in the same shape
     * InvoicePdfService::companyDetails() returns.
     *
     * @return array<string, string>
     */
    public function sellerDetails(): array
    {
        if ($this->seller_snapshot_at === null) {
            return app(\App\Services\InvoicePdfService::class)->companyDetails();
        }

        return [
            'name' => (string) $this->seller_name,
            'legal_name' => (string) $this->seller_legal_name,
            'domain' => (string) $this->seller_website,
            'address' => (string) $this->seller_address,
            'city' => (string) $this->seller_city,
            'postcode' => (string) $this->seller_postcode,
            'country' => (string) $this->seller_country,
            'phone' => (string) $this->seller_phone,
            'email' => (string) $this->seller_email,
            'tax_office' => (string) $this->seller_tax_office,
            'tax_id' => (string) $this->seller_tax_id,
            'mersis' => (string) $this->seller_mersis,
            'logo' => $this->sellerLogoFile(),
        ];
    }

    /**
     * The bank accounts printed on this invoice: frozen at issue, live fallback
     * for invoices issued before the snapshot existed.
     *
     * @return array<int, array<string, string>>
     */
    public function sellerBankAccounts(): array
    {
        if ($this->seller_snapshot_at !== null) {
            return is_array($this->seller_bank_accounts)
                ? array_values($this->seller_bank_accounts)
                : [];
        }

        $module = new \Modules\Gateways\BankTransfer\BankTransferModule();

        return $module->isActive() ? $module->accounts() : [];
    }

    /** The logo file the PDF points at: the frozen copy, or the live one for old invoices. */
    public function sellerLogoFile(): string
    {
        if ($this->seller_snapshot_at !== null) {
            $relative = trim((string) $this->seller_logo);
            if ($relative === '') {
                return '';
            }

            $path = storage_path('app/'.$relative);

            return is_file($path) ? $path : '';
        }

        return (string) (app(\App\Services\InvoicePdfService::class)->companyDetails()['logo'] ?? '');
    }

}
