<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Setting;
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
            // One key per field - the ones the general settings screen writes.
            // Each of these used to consult a second name (CompanyAddress,
            // CompanyEmail, ...) that nothing has ever written: not a screen,
            // not the installer, not a seeder. Reading them implied a
            // flexibility that did not exist, and an audit of every settings
            // key against its writers is what found this file's dead logo.
            'address' => trim((string) Setting::get('Address', '')),
            'city' => trim((string) Setting::get('CompanyCity', '')),
            'country' => trim((string) Setting::get('Country', '')),
            'phone' => trim((string) Setting::get('PhoneNumber', '')),
            'email' => trim((string) Setting::get('Email', '')),
            'tax_id' => trim((string) Setting::get('TaxID', '')),
            'logo' => $this->logoFile(),
        ];
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
        $invoice->load('client', 'items');

        $company = $this->companyDetails();

        return Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'company' => $company,
        ])->setPaper('a4');
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
