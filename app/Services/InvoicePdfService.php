<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Setting;
use App\Services\AddonManager;
use Barryvdh\DomPDF\Facade\Pdf;
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
     * Replace the template's {placeholders} with their values. Anything left
     * over is not a known placeholder and is left untouched, so a stray brace
     * cannot break the document.
     *
     * @param  array<string, string>  $vars
     */
    public function merge(string $template, array $vars): string
    {
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

        $ksef ??= $this->ksefInfo($invoice);
        $vars['ksef_number'] = $ksef['number'] ?? '';
        $vars['ksef_qr'] = ! empty($ksef['qr']) ? '<img src="'.$ksef['qr'].'" alt="">' : '';

        return $vars;
    }

    /** The invoice line items as a table, styled by the template's own CSS. */
    protected function itemsHtml(Invoice $invoice): string
    {
        $rows = '';
        foreach ($invoice->items as $item) {
            $rows .= '<tr>'
                .'<td>'.e($item->description).'</td>'
                .'<td class="num">'.(int) $item->qty.'</td>'
                .'<td class="num">'.e(invoice_money_fmt((float) $item->amount, $invoice)).'</td>'
                .'<td class="num">'.e(invoice_money_fmt((float) $item->amount * (int) $item->qty, $invoice)).'</td>'
                .'</tr>';
        }

        return '<table class="invoice-items"><thead><tr>'
            .'<th>'.e(__('common.table.description')).'</th>'
            .'<th class="num">'.e(__('common.table.qty')).'</th>'
            .'<th class="num">'.e(__('common.table.price')).'</th>'
            .'<th class="num">'.e(__('common.table.total')).'</th>'
            .'</tr></thead><tbody>'.$rows.'</tbody></table>';
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
            'items', 'ksef_number', 'ksef_qr',
        ];
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
