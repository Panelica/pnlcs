<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Invoice;
use App\Models\Setting;

/*
 * The general settings screen has two separate numbering schemes. It used to
 * ask for the default invoice type (proforma when the scheme is on) and to
 * read the latest row without a type, so the VAT box showed the proforma
 * number in both "last" and "preview".
 */

function numberingAdmin(): Admin
{
    return Admin::factory()->create([
        'role_id' => AdminRole::factory()->fullAdmin()->create()->id,
    ]);
}

it('shows the VAT and proforma series separately in the settings previews', function () {
    Setting::set('ProformaEnabled', '1');
    Setting::set('InvoiceNumberFormat', 'FV-{year}/{month}-{num}');
    Setting::set('ProformaNumberFormat', 'PRO-{year}/{month}-{num}');
    Setting::set('InvoiceNumberYearlyReset', '1');

    $month = now()->format('Y/m');

    Invoice::factory()->create([
        'invoice_num' => "FV-{$month}-000007",
        'type' => 'vat',
        'date' => now(),
        'status' => 'paid',
    ]);
    Invoice::factory()->create([
        'invoice_num' => "PRO-{$month}-000012",
        'type' => 'proforma',
        'date' => now(),
        'status' => 'cancelled',
    ]);

    $response = $this->actingAs(numberingAdmin(), 'admin')
        ->get(route('admin.settings.general'))
        ->assertOk();

    expect($response->viewData('invoiceLast'))->toBe("FV-{$month}-000007")
        ->and($response->viewData('invoicePreview'))->toBe("FV-{$month}-000008")
        ->and($response->viewData('invoiceNextSeq'))->toBe(8)
        ->and($response->viewData('proformaLast'))->toBe("PRO-{$month}-000012")
        ->and($response->viewData('proformaPreview'))->toBe("PRO-{$month}-000013");
});
