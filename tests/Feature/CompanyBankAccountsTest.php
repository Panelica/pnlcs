<?php

use App\Models\GatewaySettings;
use App\Models\Invoice;
use App\Models\Setting;
use App\Services\InvoicePdfService;

/*
 * The {company_bank_accounts} placeholder: the bank accounts configured in the
 * bank-transfer module, one row each with its bank name, currency and number.
 * Escaped, empty when no accounts are configured, and only rendered where the
 * template puts the placeholder.
 */

function seedBankAccounts(array $extra = []): void
{
    $defaults = [
        'account_name' => '24BOX Igor Rybicki',
        'bank_active' => '1',
        'bank_name' => 'Nest Bank',
        'bank_currency' => 'PLN',
        'iban' => 'PL 63 2530 0008 2096 1022 3191 0001',
    ];

    foreach (array_merge($defaults, $extra) as $setting => $value) {
        GatewaySettings::updateOrCreate(['gateway' => 'banktransfer', 'setting' => $setting], ['value' => $value]);
    }
}

it('renders one bank account', function () {
    seedBankAccounts();

    $invoice = Invoice::factory()->create();
    $html = app(InvoicePdfService::class)->placeholderVars($invoice)['company_bank_accounts'];

    expect($html)
        ->toContain('Nest Bank')
        ->toContain('PLN')
        ->toContain('PL 63 2530 0008 2096 1022 3191 0001')
        ->toContain('24BOX Igor Rybicki');
});

it('renders multiple bank accounts', function () {
    seedBankAccounts([
        'bank2_active' => '1',
        'bank2_name' => 'Nest Bank EUR',
        'bank2_currency' => 'EUR',
        'bank2_iban' => 'DE89 3704 0044 0532 0130 00',
    ]);

    $invoice = Invoice::factory()->create();
    $html = app(InvoicePdfService::class)->placeholderVars($invoice)['company_bank_accounts'];

    expect($html)
        ->toContain('PL 63 2530 0008 2096 1022 3191 0001')
        ->toContain('DE89 3704 0044 0532 0130 00')
        ->toContain('EUR');
});

it('returns empty when no accounts are configured', function () {
    $invoice = Invoice::factory()->create();
    $html = app(InvoicePdfService::class)->placeholderVars($invoice)['company_bank_accounts'];

    expect($html)->toBe('');
});

it('escapes the values from the settings', function () {
    seedBankAccounts(['bank_name' => 'Nest <b>Bank</b> & Co']);

    $invoice = Invoice::factory()->create();
    $html = app(InvoicePdfService::class)->placeholderVars($invoice)['company_bank_accounts'];

    expect($html)
        ->toContain('Nest &lt;b&gt;Bank&lt;/b&gt; &amp; Co')
        ->not->toContain('<b>Bank</b>');
});

it('does not render when the placeholder is absent', function () {
    seedBankAccounts();

    $invoice = Invoice::factory()->create();
    $service = app(InvoicePdfService::class);
    $html = $service->merge('<h1>Doc</h1>{items}', $service->placeholderVars($invoice));

    expect($html)->not->toContain('bank-accounts');
});

it('renders a PDF containing the bank accounts', function () {
    seedBankAccounts();

    $invoice = Invoice::factory()->create();

    Setting::set('InvoiceTemplate', 'custom', 'general');
    @mkdir(dirname(InvoicePdfService::customTemplatePath()), 0755, true);
    file_put_contents(InvoicePdfService::customTemplatePath(), '<h1>Faktura</h1>{items}{company_bank_accounts}');

    $output = app(InvoicePdfService::class)->generate($invoice)->output();

    expect($output)->toStartWith('%PDF');

    @unlink(InvoicePdfService::customTemplatePath());
    Setting::set('InvoiceTemplate', 'default', 'general');
});
