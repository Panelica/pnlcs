<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Setting;
use App\Services\InvoicePdfService;
use Illuminate\Http\UploadedFile;

/*
 * The custom invoice template is data, not code. It is an uploaded HTML
 * document whose {placeholders} are replaced at render time, so a file
 * carrying PHP cannot be executed by uploading it.
 */

function invoiceTemplateCleanup(): void
{
    @unlink(InvoicePdfService::customTemplatePath());
    Setting::set('InvoiceTemplate', 'default', 'general');
    Setting::set('InvoiceTemplateName', '', 'general');
}

it('replaces placeholders with invoice data and never executes the content', function () {
    $client = Client::factory()->create();
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'invoice_num' => 'INV-123']);

    @mkdir(dirname(InvoicePdfService::customTemplatePath()), 0755, true);
    file_put_contents(InvoicePdfService::customTemplatePath(), '<h1>{{ @php echo 6*7; @endphp }}</h1><p>{invoice_number}</p><p>{total}</p>');
    Setting::set('InvoiceTemplate', 'custom', 'general');

    $service = app(InvoicePdfService::class);
    $html = $service->merge(file_get_contents(InvoicePdfService::customTemplatePath()), $service->placeholderVars($invoice));

    expect($html)->toContain('INV-123')
        ->toContain('@php echo 6*7; @endphp') // left as literal text, not run
        ->not->toContain('42');

    invoiceTemplateCleanup();
});

it('resolves conditional blocks against the placeholder value', function () {
    $service = app(InvoicePdfService::class);
    $vars = ['notes' => 'Some note', 'date_paid' => '', 'unknown' => null];

    $template = 'A{?notes}[{notes}]{/notes}B{!date_paid}[unpaid]{/date_paid}C{?unknown}[x]{/unknown}D';

    expect($service->merge($template, $vars))
        ->toBe('A[Some note]B[unpaid]CD');
});

it('distinguishes a proforma from a VAT invoice', function () {
    $service = app(InvoicePdfService::class);

    $vat = Invoice::factory()->create(['type' => 'vat', 'invoice_num' => 'INV-1']);
    $proforma = Invoice::factory()->create(['type' => 'proforma', 'invoice_num' => 'PRO-1']);

    $varsVat = $service->placeholderVars($vat);
    $varsProforma = $service->placeholderVars($proforma);

    expect($varsVat['is_proforma'])->toBe('')
        ->and($varsProforma['is_proforma'])->toBe('1')
        ->and($varsVat['invoice_type'])->toBe(__('pdf.invoice'))
        ->and($varsProforma['invoice_type'])->toBe(__('pdf.proforma'));
});

it('heads the built-in document a proforma when that is what it is', function () {
    $service = app(InvoicePdfService::class);
    $company = $service->companyDetails();

    $proforma = Invoice::factory()->create(['type' => 'proforma', 'invoice_num' => 'PRO-9']);
    $vat = Invoice::factory()->create(['type' => 'vat', 'invoice_num' => 'INV-9']);

    $render = fn (Invoice $invoice) => view('pdf.invoice', ['invoice' => $invoice, 'company' => $company, 'ksef' => null])->render();

    // The built-in template headed every document "Invoice", so a proforma
    // printed as a tax document it is not.
    expect($render($proforma))->toContain(__('pdf.proforma'))
        ->and($render($vat))->toContain(__('pdf.invoice'))
        ->and($render($vat))->not->toContain(__('pdf.proforma'));
});

it('uses the uploaded template only while one is selected and present', function () {
    $invoice = Invoice::factory()->create();
    $service = app(InvoicePdfService::class);

    expect($service->customTemplate())->toBeNull();

    @mkdir(dirname(InvoicePdfService::customTemplatePath()), 0755, true);
    file_put_contents(InvoicePdfService::customTemplatePath(), '<h1>{invoice_number}</h1>');
    Setting::set('InvoiceTemplate', 'custom', 'general');

    expect($service->customTemplate())->not->toBeNull();

    // Selecting built-in (or a missing file) falls back to the built-in view.
    Setting::set('InvoiceTemplate', 'default', 'general');
    expect($service->customTemplate())->toBeNull();

    Setting::set('InvoiceTemplate', 'custom', 'general');
    @unlink(InvoicePdfService::customTemplatePath());
    expect($service->customTemplate())->toBeNull();

    invoiceTemplateCleanup();
});

it('builds the items table from the invoice line items', function () {
    $client = Client::factory()->create();
    $invoice = Invoice::factory()->create(['client_id' => $client->id]);
    InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'client_id' => $client->id, 'description' => 'Hosting Pro', 'qty' => 1, 'amount' => 10]);

    $service = app(InvoicePdfService::class);
    $html = $service->placeholderVars($invoice)['items'];

    expect($html)->toContain('Hosting Pro')->toContain('<table');
});

it('leaves the VAT columns off an invoice that is not taxed', function () {
    $client = Client::factory()->create();
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'tax_mode' => Invoice::TAX_NONE]);
    InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'client_id' => $client->id, 'description' => 'Hosting Pro', 'qty' => 2, 'amount' => 10]);

    $html = app(InvoicePdfService::class)->placeholderVars($invoice)['items'];

    // The four-column table, as it was before the VAT table existed: no VAT
    // header, no "0%" claiming a rate, and no style block of ours over the
    // template's own CSS.
    expect($html)->toContain('Hosting Pro')
        ->not->toContain(__('pdf.items_vat_rate'))
        ->not->toContain(__('pdf.items_gross_amount'))
        ->not->toContain('<style>');
    expect(preg_match_all('/<th[ >]/', $html))->toBe(4);
});

it('builds the VAT table when the invoice is taxed', function () {
    $client = Client::factory()->create();
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'tax_mode' => null]);
    InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'client_id' => $client->id, 'description' => 'Hosting Pro', 'qty' => 1, 'amount' => 100, 'tax_rate' => 23]);

    $html = app(InvoicePdfService::class)->placeholderVars($invoice)['items'];

    expect($html)->toContain(__('pdf.items_vat_rate'))->toContain('23%');
    expect(preg_match_all('/<th[ >]/', $html))->toBe(9);
});

it('refuses an upload that is not an HTML document', function () {
    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->create(['permissions' => ['manage_settings']])->id]);

    $this->actingAs($admin, 'admin')
        ->post(route('admin.settings.invoice-template.upload'), [
            'template' => UploadedFile::fake()->create('evil.php', 10, 'application/x-php'),
        ])
        ->assertSessionHasErrors('template');

    $this->actingAs($admin, 'admin')
        ->post(route('admin.settings.invoice-template.upload'), [
            'template' => UploadedFile::fake()->create('evil.blade.php', 10, 'text/plain'),
        ])
        ->assertSessionHasErrors('template');
});

it('accepts an HTML template and removes it back to the built-in one', function () {
    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->create(['permissions' => ['manage_settings']])->id]);
    $file = UploadedFile::fake()->createWithContent('mine.html', '<h1>{invoice_number}</h1>');

    $this->actingAs($admin, 'admin')
        ->post(route('admin.settings.invoice-template.upload'), ['template' => $file])
        ->assertSessionHas('success');

    expect(Setting::get('InvoiceTemplate'))->toBe('custom');
    expect(is_file(InvoicePdfService::customTemplatePath()))->toBeTrue();

    $this->actingAs($admin, 'admin')
        ->delete(route('admin.settings.invoice-template.remove'))
        ->assertSessionHas('success');

    expect(Setting::get('InvoiceTemplate'))->toBe('default');
    expect(is_file(InvoicePdfService::customTemplatePath()))->toBeFalse();

    invoiceTemplateCleanup();
});

it('writes text into the template as text, so a name with markup cannot break the document', function () {
    $client = Client::factory()->create([
        'company_name' => 'Müller & Sohn <b>GmbH</b>',
        'first_name' => 'Ann & Co',
        'address1' => '5 "Main" Street',
    ]);
    $invoice = Invoice::factory()->create(['client_id' => $client->id]);

    $vars = app(InvoicePdfService::class)->placeholderVars($invoice);

    expect($vars['client_company'])->toBe('Müller &amp; Sohn &lt;b&gt;GmbH&lt;/b&gt;')
        ->and($vars['client_name'])->toContain('Ann &amp; Co')
        ->and($vars['client_address'])->toBe('5 &quot;Main&quot; Street')
        // The two values that are meant to be markup stay markup.
        ->and($vars['items'])->toContain('<table');
});
