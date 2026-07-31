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
            'address' => $this->firstFilled(['Address', 'CompanyAddress']),
            'city' => $this->firstFilled(['CompanyCity', 'City']),
            'country' => $this->firstFilled(['Country', 'CompanyCountry']),
            'phone' => $this->firstFilled(['PhoneNumber', 'CompanyPhone']),
            'email' => $this->firstFilled(['Email', 'CompanyEmail']),
            'tax_id' => $this->firstFilled(['TaxID']),
            'logo' => Setting::get('Logo', ''),
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
        $filename = 'invoice-'.($invoice->invoice_num ?? $invoice->id).'.pdf';

        return $pdf->download($filename);
    }
}
