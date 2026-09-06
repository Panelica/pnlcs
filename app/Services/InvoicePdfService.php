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
            'domain' => Setting::get('Domain', ''),
            // The screen's key first, then the older hand-set name. The
            // fallbacks are not writable from any screen, but honouring a value
            // someone put straight into the database is a deliberate, tested
            // decision (CompanyDetailsTest) - the same stance the logo takes.
            'address' => $this->firstFilled(['Address', 'CompanyAddress']),
            'city' => $this->firstFilled(['CompanyCity', 'City']),
            'country' => $this->firstFilled(['Country', 'CompanyCountry']),
            'phone' => $this->firstFilled(['PhoneNumber', 'CompanyPhone']),
            'email' => $this->firstFilled(['Email', 'CompanyEmail']),
            'tax_id' => $this->firstFilled(['TaxID']),
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

        return Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'company' => $company,
            'ksef' => $this->ksefInfo($invoice),
        ])->setPaper('a4');
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

        if (! $ksef || ! filled($ksef->ksef_number)) {
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
        $env = (string) config('ksef.environment', 'prod');
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
