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
