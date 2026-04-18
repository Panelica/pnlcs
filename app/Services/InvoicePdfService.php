<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoicePdfService
{
    public function generate(Invoice $invoice): \Barryvdh\DomPDF\PDF
    {
        $invoice->load('client', 'items');

        $company = [
            'name'    => Setting::get('CompanyName', 'PNLCS'),
            'domain'  => Setting::get('Domain', ''),
            'address' => Setting::get('CompanyAddress', ''),
            'city'    => Setting::get('CompanyCity', ''),
            'country' => Setting::get('CompanyCountry', ''),
            'phone'   => Setting::get('CompanyPhone', ''),
            'email'   => Setting::get('CompanyEmail', ''),
            'tax_id'  => Setting::get('TaxID', ''),
            'logo'    => Setting::get('Logo', ''),
        ];

        return Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'company' => $company,
        ])->setPaper('a4');
    }

    public function download(Invoice $invoice): \Symfony\Component\HttpFoundation\Response
    {
        $pdf = $this->generate($invoice);
        $filename = 'invoice-' . ($invoice->invoice_num ?? $invoice->id) . '.pdf';

        return $pdf->download($filename);
    }
}
