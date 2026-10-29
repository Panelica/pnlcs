<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\GatewaySettings;
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

it('picks the payment QR account by the customer country', function () {
    Setting::set('Country', 'PL', 'general');

    // Slot 0 in the company currency (PLN), slot 1 in EUR.
    GatewaySettings::updateOrCreate(['gateway' => 'banktransfer', 'setting' => 'account_name'], ['value' => '24 Box Igor Rybicki']);
    GatewaySettings::updateOrCreate(['gateway' => 'banktransfer', 'setting' => 'bank_name'], ['value' => 'Nest Bank']);
    GatewaySettings::updateOrCreate(['gateway' => 'banktransfer', 'setting' => 'iban'], ['value' => 'PL 63 2530 0008 2096 1022 3191 0001']);
    GatewaySettings::updateOrCreate(['gateway' => 'banktransfer', 'setting' => 'bank2_name'], ['value' => 'EUR Bank']);
    GatewaySettings::updateOrCreate(['gateway' => 'banktransfer', 'setting' => 'bank2_iban'], ['value' => 'DE89370400440532013000']);

    $service = app(InvoicePdfService::class);

    $pl = Invoice::factory()->create(['client_id' => Client::factory()->create(['country' => 'PL'])->id, 'invoice_num' => 'INV-1']);
    $de = Invoice::factory()->create(['client_id' => Client::factory()->create(['country' => 'DE'])->id, 'invoice_num' => 'INV-2']);
    $us = Invoice::factory()->create(['client_id' => Client::factory()->create(['country' => 'US'])->id, 'invoice_num' => 'INV-3']);

    // Local -> first account (ZBP), SEPA -> second (EPC), elsewhere -> none.
    expect($service->placeholderVars($pl)['payment_qr'])->toStartWith('<img src="data:image/png;base64,')
        ->and($service->placeholderVars($de)['payment_qr'])->toStartWith('<img src="data:image/png;base64,')
        ->and($service->placeholderVars($us)['payment_qr'])->toBe('');
});

it('renders no payment QR when no bank is configured', function () {
    $service = app(InvoicePdfService::class);
    $client = Client::factory()->create(['country' => 'PL']);
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'invoice_num' => 'INV-1']);

    expect($service->placeholderVars($invoice)['payment_qr'])->toBe('');
});

it('builds a ZBP payload for Polish customers and an EPC payload otherwise', function () {
    $service = app(InvoicePdfService::class);
    $invoice = Invoice::factory()->create(['invoice_num' => 'INV-1', 'total' => 123.45]);

    $nrb = (new ReflectionMethod($service, 'nrb'))->invoke($service, 'PL 63 2530 0008 2096 1022 3191 0001');
    expect($nrb)->toBe('63253000082096102231910001');

    $zbp = (new ReflectionMethod($service, 'zbpPayload'))->invoke($service, '24 Box Igor Rybicki', '63253000082096102231910001', '1234567890', 'PL', $invoice);
    expect($zbp)->toBe('1234567890|PL|63253000082096102231910001|012345|24 Box Igor Rybicki|INV-1|||');

    $epc = (new ReflectionMethod($service, 'epcPayload'))->invoke($service, '24 Box Igor Rybicki', 'PL63253000082096102231910001', 'NESBPLPW', $invoice);
    expect($epc)->toStartWith("BCD\n002\n1\nSCT\nNESBPLPW\n24 Box Igor Rybicki\n")
        ->toContain('INV-1');
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
