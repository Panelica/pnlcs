<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Setting;
use App\Models\TaxRule;
use App\Services\AddonManager;
use Barryvdh\DomPDF\Facade\Pdf;
use Modules\Gateways\BankTransfer\BankTransferModule;
use Symfony\Component\HttpFoundation\Response;

class InvoicePdfService
{
    /**
     * The company block printed on the invoice.
     *
     * Settings — General saves the address as Address, the phone as
     * PhoneNumber and the email as Email. The Company* names are what older
     * installations set by hand, so they still count when the screen's own
     * field is empty.
     *
     * @return array<string, string>
     */
    public function companyDetails(): array
    {
        return [
            'name' => company_name(),
            // The registered company title, which is not the brand. An invoice
            // that will be fed to an official e-invoicing integration has to
            // carry the legal entity, not the trading name.
            'legal_name' => $this->firstFilled(['CompanyLegalName']),
            'domain' => Setting::get('Domain', ''),
            // The screen's key first, then the older hand-set name. The
            // fallbacks are not writable from any screen, but honouring a value
            // someone put straight into the database is a deliberate, tested
            // decision (CompanyDetailsTest) - the same stance the logo takes.
            'address' => $this->firstFilled(['Address', 'CompanyAddress']),
            'city' => $this->firstFilled(['CompanyCity', 'City']),
            'postcode' => $this->firstFilled(['Postcode']),
            'country' => $this->firstFilled(['Country', 'CompanyCountry']),
            'phone' => $this->firstFilled(['PhoneNumber', 'CompanyPhone']),
            'email' => $this->firstFilled(['Email', 'CompanyEmail']),
            'tax_office' => $this->firstFilled(['TaxOffice']),
            'tax_id' => $this->firstFilled(['TaxID']),
            'mersis' => $this->firstFilled(['MersisNo']),
            'logo' => $this->logoFile(),
        ];
    }

    /** @param array<int, string> $keys */
    private function firstFilled(array $keys): string
    {
        foreach ($keys as $key) {
            $value = trim((string) Setting::get($key, ''));

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * The logo as a file on disk, or nothing.
     *
     * The 'Logo' key was read here for as long as this service existed, and no
     * screen has ever written it - the appearance screen writes
     * custom_logo_path - so no invoice ever carried a logo. Both are honoured
     * now, and the answer is a filesystem path because the PDF renderer does
     * not fetch URLs: a web path that looks right would render a broken image.
     * A recorded logo whose file has since gone renders as no logo, not as a
     * broken invoice.
     */
    private function logoFile(): string
    {
        foreach (['Logo', 'custom_logo_path'] as $key) {
            $web = trim((string) Setting::get($key, ''));
            if ($web === '' || str_contains($web, '..')) {
                continue;
            }
            $file = public_path(ltrim($web, '/'));
            if (is_file($file)) {
                return $file;
            }
        }

        return '';
    }


    public function generate(Invoice $invoice): \Barryvdh\DomPDF\PDF
    {
        $invoice->load('client', 'items', 'ksefInvoice');

        $company = $this->companyDetails();
        $ksef = $this->ksefInfo($invoice);

        $template = $this->customTemplate();
        if ($template !== null) {
            // The uploaded template is a plain HTML document whose {placeholders}
            // are replaced here. It is data, never code: nothing in it runs.
            $html = $this->merge(
                (string) file_get_contents($template),
                $this->placeholderVars($invoice, $company, $ksef)
            );

            return Pdf::loadHTML($html)->setPaper('a4');
        }

        return Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'company' => $company,
            'ksef' => $ksef,
        ])->setPaper('a4');
    }

    /**
     * The uploaded custom template's path, or null when none is in use.
     * Selected through Settings — General (InvoiceTemplate), the file is kept
     * on the private disk so it is never served by the web server.
     */
    public function customTemplate(): ?string
    {
        if ((string) Setting::get('InvoiceTemplate', 'default') !== 'custom') {
            return null;
        }

        return is_file(self::customTemplatePath()) ? self::customTemplatePath() : null;
    }

    /** Where an uploaded custom invoice template lives. */
    public static function customTemplatePath(): string
    {
        return storage_path('app/invoice-templates/custom.html');
    }

    /**
     * Replace the template's {placeholders} with their values and resolve its
     * conditional blocks. Anything left over is not a known placeholder and is
     * left untouched, so a stray brace cannot break the document.
     *
     * A block {?key} … {/key} renders only when {key} has a value;
     * {!key} … {/key} renders only when it is empty. The test is against the
     * value, so it stays data, never code — nothing in the template runs.
     *
     * @param  array<string, string>  $vars
     */
    public function merge(string $template, array $vars): string
    {
        $block = '/\{(?<mark>[?!])(?<key>[a-z0-9_]+)\}(?<body>.*?)\{\/\k<key>\}/s';

        // Resolve blocks innermost-first until none remain, so a block shown
        // around another block is still processed.
        $count = 0;
        do {
            $template = preg_replace_callback($block, function (array $m) use ($vars): string {
                $empty = ! filled($vars[$m['key']] ?? null);
                $show = $m['mark'] === '!' ? $empty : ! $empty;

                return $show ? $m['body'] : '';
            }, $template, -1, $count);
        } while ($count > 0);

        foreach ($vars as $key => $value) {
            $template = str_replace('{'.$key.'}', (string) $value, $template);
        }

        return $template;
    }

    /**
     * The values the custom template can refer to. The keys are the vocabulary
     * listed on the settings screen (see customTemplateVocabulary).
     *
     * @return array<string, string>
     */
    public function placeholderVars(Invoice $invoice, ?array $company = null, ?array $ksef = null): array
    {
        $company ??= $this->companyDetails();
        $buyer = fn (string $field) => (string) $invoice->buyer($field);

        $logo = $company['logo'] ?? '';

        $vars = [
            'company_name' => $company['name'],
            'company_legal_name' => $company['legal_name'],
            'company_domain' => $company['domain'],
            'company_address' => $company['address'],
            'company_city' => $company['city'],
            'company_postcode' => $company['postcode'],
            'company_country' => $company['country'],
            'company_phone' => $company['phone'],
            'company_email' => $company['email'],
            'company_tax_office' => $company['tax_office'],
            'company_tax_id' => $company['tax_id'],
            'company_mersis' => $company['mersis'],
            'company_logo' => $logo !== '' ? '<img src="'.e($logo).'" alt="">' : '',

            'client_name' => trim($buyer('first_name').' '.$buyer('last_name')),
            'client_company' => $buyer('company_name'),
            'client_address' => $buyer('address1'),
            'client_address2' => $buyer('address2'),
            'client_city' => $buyer('city'),
            'client_state' => $buyer('state'),
            'client_postcode' => $buyer('postcode'),
            'client_country' => $buyer('country'),
            'client_tax_id' => $buyer('tax_id'),
            'client_email' => $buyer('email'),

            'invoice_number' => (string) ($invoice->invoice_num ?? $invoice->id),
            'invoice_date' => $invoice->date?->format(date_fmt()) ?? '',
            'due_date' => $invoice->due_date?->format(date_fmt()) ?? '',
            'date_paid' => $invoice->date_paid?->format(date_fmt()) ?? '',
            'status' => invoice_status_label($invoice->status),
            'payment_method' => $invoice->payment_method ? payment_method_label((string) $invoice->payment_method) : '',
            'subtotal' => invoice_money_fmt((float) $invoice->subtotal, $invoice),
            'tax' => invoice_money_fmt((float) $invoice->tax, $invoice),
            'tax2' => invoice_money_fmt((float) $invoice->tax2, $invoice),
            'credit' => invoice_money_fmt((float) $invoice->credit, $invoice),
            'total' => invoice_money_fmt((float) $invoice->total, $invoice),
            'notes' => $invoice->notes ? nl2br(e($invoice->notes)) : '',
            'items' => $this->itemsHtml($invoice),
        ];

        // Everything above is text the customer or the operator typed, and it
        // is about to become HTML: a company called "Müller & Sohn" must read
        // that way in the PDF, not break the document around it. The two
        // values that ARE markup - the logo tag and the items table - are
        // built here and escaped as they are built.
        $markup = ['company_logo', 'items', 'notes'];
        foreach ($vars as $key => $value) {
            if (! in_array($key, $markup, true)) {
                $vars[$key] = e((string) $value);
            }
        }

        $ksef ??= $this->ksefInfo($invoice);
        $vars['ksef_number'] = e((string) ($ksef['number'] ?? ''));
        $vars['ksef_qr'] = ! empty($ksef['qr']) ? '<img src="'.e((string) $ksef['qr']).'" alt="">' : '';
        $vars['payment_qr'] = $this->paymentQr($invoice);

        return $vars;
    }

    /**
     * The invoice line items as the 9-column table the {items} placeholder
     * emits. The table carries its own style block and classes so a custom
     * template's CSS cannot flatten it, and every value is escaped.
     */
    protected function itemsHtml(Invoice $invoice): string
    {
        $rows = '';
        $index = 0;
        foreach ($invoice->items as $item) {
            $index++;
            $line = $invoice->lineTax($item);
            $rate = (float) $line['rate'];
            $unitNet = $this->unitNetPrice($invoice, $item, $rate);
            $net = (float) $line['net'];
            $vat = (float) $line['vat'];

            $rows .= '<tr>'
                .'<td class="c">'.$index.'</td>'
                .'<td>'.e((string) $item->description).'</td>'
                .'<td class="c">'.max(1, (int) ($item->qty ?? 1)).'</td>'
                .'<td class="c">'.e((string) ($item->unit ?? '')).'</td>'
                .'<td class="num">'.e($this->lineAmount($invoice, $unitNet)).'</td>'
                .'<td class="c">'.e($this->vatLabel($item, $rate)).'</td>'
                .'<td class="num">'.e($this->lineAmount($invoice, $vat)).'</td>'
                .'<td class="num">'.e($this->lineAmount($invoice, $net)).'</td>'
                .'<td class="num">'.e($this->lineAmount($invoice, $net + $vat)).'</td>'
                .'</tr>';
        }

        return $this->itemsTableStyle()
            .'<table class="invoice-items"><thead><tr>'
            .'<th class="lp">'.e(__('pdf.items_lp')).'</th>'
            .'<th class="name">'.e(__('pdf.items_name')).'</th>'
            .'<th class="qty">'.e(__('pdf.items_qty')).'</th>'
            .'<th class="unit">'.e(__('pdf.items_unit')).'</th>'
            .'<th class="net-price">'.e(__('pdf.items_net_price')).'</th>'
            .'<th class="vat-rate">'.e(__('pdf.items_vat_rate')).'</th>'
            .'<th class="vat-amount">'.e(__('pdf.items_vat_amount')).'</th>'
            .'<th class="net-amount">'.e(__('pdf.items_net_amount')).'</th>'
            .'<th class="gross-amount">'.e(__('pdf.items_gross_amount')).'</th>'
            .'</tr></thead><tbody>'.$rows.'</tbody></table>';
    }

    /** The self-contained stylesheet for the {items} table. */
    protected function itemsTableStyle(): string
    {
        return '<style>'
            .'table.invoice-items{width:100%;border-collapse:collapse;font-size:8.5pt;table-layout:fixed;}'
            .'table.invoice-items th,table.invoice-items td{border:0.5pt solid #111;padding:2pt 4pt;vertical-align:top;}'
            .'table.invoice-items thead th{background:#eaeaea;text-align:center;font-weight:bold;line-height:1.15;word-wrap:break-word;overflow-wrap:break-word;}'
            .'table.invoice-items td{word-wrap:break-word;overflow-wrap:break-word;}'
            .'table.invoice-items tbody tr{page-break-inside:avoid;}'
            .'table.invoice-items th.lp{width:4%;}'
            .'table.invoice-items th.name{width:29%;}'
            .'table.invoice-items th.qty{width:7%;}'
            .'table.invoice-items th.unit{width:6%;}'
            .'table.invoice-items th.net-price{width:12%;}'
            .'table.invoice-items th.vat-rate{width:7%;}'
            .'table.invoice-items th.vat-amount{width:11%;}'
            .'table.invoice-items th.net-amount{width:11%;}'
            .'table.invoice-items th.gross-amount{width:13%;}'
            .'table.invoice-items td.c{text-align:center;}'
            .'table.invoice-items td.num{text-align:right;}'
            .'</style>';
    }

    /** The unit net price: gross less its tax share when prices include tax. */
    protected function unitNetPrice(Invoice $invoice, InvoiceItem $item, float $rate): float
    {
        $unit = (float) $item->amount;

        return $invoice->tax_mode === Invoice::TAX_INCLUSIVE
            ? $unit - TaxRule::taxShare($unit, $rate, true)
            : $unit;
    }

    /**
     * The VAT column value: the line's rate ("23%"), or its label ("zw.",
     * "np.") when there is no rate. The tax rule's full name ("VAT 23%") is
     * the VAT-breakdown group, not what the column prints.
     */
    protected function vatLabel(InvoiceItem $item, float $rate): string
    {
        if ($rate <= 0) {
            return $item->tax_label ?: '0%';
        }

        return rtrim(rtrim(number_format($rate, 2), '0'), '.').'%';
    }

    /** An amount as a bare number in the invoice's currency, no symbol. */
    protected function lineAmount(Invoice $invoice, float|int|string|null $amount): string
    {
        $code = strtoupper((string) ($invoice->source_currency ?? ''));
        if ($code !== '') {
            $currency = Currency::where('code', $code)->first();
            if ($currency) {
                return $currency->number($amount);
            }
        }

        return number_format((float) $amount, 2, ',', ' ');
    }

    /**
     * The placeholder vocabulary an uploaded template can use, for the
     * settings screen to list.
     *
     * @return list<string>
     */
    public static function customTemplateVocabulary(): array
    {
        return [
            'company_name', 'company_legal_name', 'company_domain', 'company_address',
            'company_city', 'company_postcode', 'company_country', 'company_phone',
            'company_email', 'company_tax_office', 'company_tax_id', 'company_mersis',
            'company_logo',
            'client_name', 'client_company', 'client_address', 'client_address2',
            'client_city', 'client_state', 'client_postcode', 'client_country',
            'client_tax_id', 'client_email',
            'invoice_number', 'invoice_date', 'due_date', 'date_paid', 'status',
            'payment_method', 'subtotal', 'tax', 'tax2', 'credit', 'total', 'notes',
            'items', 'ksef_number', 'ksef_qr', 'payment_qr',
        ];
    }

    /**
     * Countries an EPC SEPA transfer can be made to (the SEPA zone). A customer
     * from anywhere else gets no payment QR.
     */
    protected const SEPA_COUNTRIES = [
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR',
        'HU', 'IS', 'IE', 'IT', 'LV', 'LI', 'LT', 'LU', 'MT', 'MC', 'NL', 'NO',
        'PL', 'PT', 'RO', 'SM', 'SK', 'SI', 'ES', 'SE', 'CH', 'GB', 'AD', 'VA',
    ];

    /**
     * A payment QR code as an inline <img>, from the bank-transfer module. The
     * customer's country decides the account and the payload:
     *
     * - same country as the company: the first account (company currency),
     *   encoded as the Polish ZBP "Przyjazny Przelew" (an NRB);
     * - elsewhere in the SEPA zone: the second account (EUR), encoded as the
     *   EPC SEPA payload (an IBAN);
     * - anywhere else: no QR.
     */
    public function paymentQr(Invoice $invoice): string
    {
        $module = new BankTransferModule;
        $companyCountry = strtoupper((string) Setting::get('Country', ''));
        $clientCountry = strtoupper((string) $invoice->buyer('country'));

        $local = $companyCountry !== '' && $clientCountry === $companyCountry;
        $sepa = ! $local && in_array($clientCountry, self::SEPA_COUNTRIES, true);

        if (! $local && ! $sepa) {
            return '';
        }

        $bank = $module->bank($local ? 0 : 1);
        if ($bank === null) {
            return '';
        }

        $holder = (string) ($bank['holder'] ?? '');
        if ($holder === '') {
            $holder = company_name();
        }
        $iban = (string) ($bank['iban'] ?? '');
        $account = (string) ($bank['account_number'] ?? '');
        $swift = (string) ($bank['swift'] ?? '');

        if ($holder === '') {
            return '';
        }

        $payload = '';
        if ($local) {
            $nrb = $this->nrb($account !== '' ? $account : $iban);
            if ($nrb !== '') {
                $payload = $this->zbpPayload($holder, $nrb, $invoice);
            }
        }

        if ($payload === '' && $iban !== '') {
            $payload = $this->epcPayload($holder, $iban, $swift, $invoice);
        }

        if ($payload === '') {
            return '';
        }

        $uri = $this->qrDataUri($payload);

        return $uri !== '' ? '<img src="'.$uri.'" alt="">' : '';
    }

    /** The 26-digit NRB from an NRB or a PL-prefixed IBAN; '' when foreign. */
    protected function nrb(string $ibanOrAccount): string
    {
        $digits = preg_replace('/\D/', '', $ibanOrAccount) ?? '';
        $digits = strlen($digits) === 28 ? substr($digits, 2) : $digits;

        return preg_match('/^\d{26}$/', $digits) === 1 ? $digits : '';
    }

    /** The Polish ZBP ("Przyjazny Przelew") payload: pipe-delimited fields. */
    protected function zbpPayload(string $holder, string $account, Invoice $invoice): string
    {
        $name = mb_substr($holder, 0, 20);
        $amount = number_format((float) $invoice->amountDue(), 2, ',', '');
        $title = mb_substr((string) ($invoice->invoice_num ?? $invoice->id), 0, 32);

        return '|'.$name.'|'.$account.'|'.$amount.'|'.$title.'|';
    }

    /** The EPC SEPA payload (BCD tag), one field per line, amount in EUR. */
    protected function epcPayload(string $holder, string $iban, string $swift, Invoice $invoice): string
    {
        if ($iban === '') {
            return '';
        }

        $iban = strtoupper((string) preg_replace('/\s+/', '', $iban));
        $name = mb_substr($holder, 0, 70);
        $amount = number_format($this->eurAmount($invoice), 2, '.', '');
        $title = mb_substr((string) ($invoice->invoice_num ?? $invoice->id), 0, 35);

        return "BCD\n002\n1\nSCT\n{$swift}\n{$name}\n{$iban}\nEUR{$amount}\n\n\n{$title}\n";
    }

    /** The amount due in EUR: converted when the invoice bills in EUR. */
    protected function eurAmount(Invoice $invoice): float
    {
        $amount = $invoice->amountDue();

        if (strtoupper((string) $invoice->billing_currency) === 'EUR' && (float) $invoice->exchange_rate > 0) {
            return round($amount * (float) $invoice->exchange_rate, 2);
        }

        return $amount;
    }

    /**
     * The KSeF number and verification QR for an invoice, or null when the
     * KSeF addon is not active or the invoice has not been accepted yet.
     *
     * KSeF is a Polish-only scheme; on installations where the addon is turned
     * off nothing is printed, so invoices still render cleanly everywhere.
     *
     * @return array{number: string, qr: string}|null
     */
    public function ksefInfo(Invoice $invoice): ?array
    {
        if (! app(AddonManager::class)->isActive('ksef')) {
            return null;
        }

        $ksef = $invoice->ksefInvoice;

        // Accepted only: rows saved before this check could carry the send
        // reference as their number on a rejected or unconfirmed invoice.
        if (! $ksef || ! filled($ksef->ksef_number) || ! filled($ksef->accepted_at)) {
            return null;
        }

        $number = (string) $ksef->ksef_number;
        $url = $this->ksefVerifyUrl($number);

        return [
            'number' => $number,
            'qr' => $this->qrDataUri($url),
        ];
    }

    /**
     * The official KSeF verification URL encoded into the QR. Test
     * environments point at ksef-test so a scanned code is not mistaken for a
     * production invoice.
     */
    private function ksefVerifyUrl(string $number): string
    {
        // The environment the operator chose in the addon's settings. The
        // config value is only the module's file default ("integration"), so
        // reading it pointed production invoices at the test site.
        $env = class_exists(\Modules\Ksef\KsefSettings::class)
            ? (string) \Modules\Ksef\KsefSettings::resolve()['environment']
            : (string) config('ksef.environment', 'prod');
        $base = in_array($env, ['integration', 'demo'], true)
            ? 'https://ksef-test.mf.gov.pl/web/verify'
            : 'https://ksef.mf.gov.pl/web/verify';

        return $base.'?nr='.rawurlencode($number);
    }

    /**
     * The QR code as an inline PNG data URI, ready for the PDF renderer (which
     * does not fetch remote URLs). Empty string when generation fails, so an
     * invoice never breaks on a missing image extension.
     */
    private function qrDataUri(string $content): string
    {
        try {
            $renderer = new \BaconQrCode\Renderer\GDLibRenderer(200, 2);
            $png = (new \BaconQrCode\Writer($renderer))->writeString($content);

            return 'data:image/png;base64,'.base64_encode($png);
        } catch (\Throwable) {
            return '';
        }
    }

    public function download(Invoice $invoice): Response
    {
        $pdf = $this->generate($invoice);

        // The numbering scheme may put "/" or separators in the invoice
        // number, which the file name cannot carry.
        $num = str_replace(['/', '\\'], '-', (string) ($invoice->invoice_num ?? $invoice->id));
        $filename = "invoice-{$num}.pdf";

        return $pdf->download($filename);
    }
}
