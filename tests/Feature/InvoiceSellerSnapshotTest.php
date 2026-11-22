<?php

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Setting;
use App\Services\InvoicePdfService;
use App\Services\InvoiceService;
use Modules\Ksef\Support\InvoiceXmlBuilder;

/*
 * The invoice already froze the buyer and the money. The seller block was
 * still read live from the settings when the PDF was printed, so changing the
 * company details, logo or bank accounts rewrote every past invoice. These
 * pin the seller snapshot: taken at issue time, never changed by later
 * settings, with a live fallback for invoices issued before the snapshot.
 */

function pnlcs_test_seller_settings(array $values): void
{
    $keys = [
        'name' => ['whitelabel_company_name', 'CompanyName'],
        'legal_name' => ['CompanyLegalName'],
        'website' => ['CompanyWebsite'],
        'address' => ['Address'],
        'city' => ['CompanyCity'],
        'postcode' => ['Postcode'],
        'country' => ['Country'],
        'phone' => ['PhoneNumber'],
        'email' => ['Email'],
        'tax_office' => ['TaxOffice'],
        'tax_id' => ['TaxID'],
        'mersis' => ['MersisNo'],
        'logo' => ['custom_logo_path'],
    ];

    foreach ($values as $field => $value) {
        foreach ($keys[$field] ?? [$field] as $key) {
            Setting::set($key, (string) $value);
        }
    }

    // company_name() memoises the answer for the request.
    app()->forgetInstance('pnlcs.company_name');
}

beforeEach(function () {
    pnlcs_test_seller_settings([
        'name' => 'Old Seller',
        'legal_name' => 'Old Seller Sp. z o.o.',
        'website' => 'old.example.test',
        'address' => 'Old Street 1',
        'city' => 'Warsaw',
        'postcode' => '00-001',
        'country' => 'PL',
        'phone' => '+48 100 200 300',
        'email' => 'old@example.test',
        'tax_office' => 'US Warsaw',
        'tax_id' => '1111111111',
    ]);
});

afterEach(function () {
    @unlink(public_path('pnlcs-test-logo.png'));
    @unlink(public_path('pnlcs-test-logo-new.png'));
});

it('freezes the seller when the invoice is issued', function () {
    $client = Client::factory()->create();
    $invoice = app(InvoiceService::class)->createInvoice($client, [
        ['description' => 'Hosting', 'amount' => 100],
    ]);

    expect($invoice->seller_snapshot_at)->not->toBeNull()
        ->and($invoice->seller_name)->toBe('Old Seller')
        ->and($invoice->seller_legal_name)->toBe('Old Seller Sp. z o.o.')
        ->and($invoice->seller_address)->toBe('Old Street 1')
        ->and($invoice->seller_tax_id)->toBe('1111111111');

    pnlcs_test_seller_settings([
        'name' => 'New Seller',
        'address' => 'New Street 9',
        'tax_id' => '2222222222',
    ]);

    $invoice->refresh();
    $details = $invoice->sellerDetails();

    expect($details['name'])->toBe('Old Seller')
        ->and($details['address'])->toBe('Old Street 1')
        ->and($details['tax_id'])->toBe('1111111111');

    // The rendered document reads the snapshot too, not the setting.
    $vars = app(InvoicePdfService::class)->placeholderVars($invoice);
    expect($vars['company_name'])->toBe('Old Seller')
        ->and($vars['company_address'])->toBe('Old Street 1');
});

it('freezes the seller when a draft is published, not when it is started', function () {
    $client = Client::factory()->create();
    $invoice = app(InvoiceService::class)->createInvoice($client, [
        ['description' => 'Hosting', 'amount' => 100],
    ], ['status' => InvoiceStatus::Draft->value]);

    expect($invoice->seller_snapshot_at)->toBeNull();

    pnlcs_test_seller_settings(['name' => 'Published Seller', 'address' => 'Published Street 2']);

    app(InvoiceService::class)->publishDraft($invoice);
    $invoice->refresh();

    expect($invoice->seller_snapshot_at)->not->toBeNull()
        ->and($invoice->seller_name)->toBe('Published Seller')
        ->and($invoice->seller_address)->toBe('Published Street 2');
});

it('still renders invoices issued before the seller snapshot existed', function () {
    $client = Client::factory()->create();
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'seller_snapshot_at' => null,
    ]);

    expect($invoice->sellerDetails()['name'])->toBe('Old Seller')
        ->and($invoice->seller('tax_id'))->toBe('1111111111')
        ->and($invoice->sellerBankAccounts())->toBeArray();
});

it('copies the logo into the invoice and keeps it after the shop logo changes', function () {
    file_put_contents(public_path('pnlcs-test-logo.png'), 'old logo bytes');
    file_put_contents(public_path('pnlcs-test-logo-new.png'), 'new logo bytes');

    pnlcs_test_seller_settings(['logo' => '/pnlcs-test-logo.png']);

    $client = Client::factory()->create();
    $invoice = app(InvoiceService::class)->createInvoice($client, [
        ['description' => 'Hosting', 'amount' => 100],
    ]);

    expect($invoice->seller_logo)->not->toBeNull();
    $frozen = $invoice->sellerLogoFile();
    expect(is_file($frozen))->toBeTrue();

    pnlcs_test_seller_settings(['logo' => '/pnlcs-test-logo-new.png']);

    expect($invoice->fresh()->sellerLogoFile())->toBe($frozen);

    @unlink($frozen);
});

it('snapshots the bank accounts shown at issue time', function () {
    $snapshot = Invoice::sellerSnapshotFrom(
        ['name' => 'Seller'],
        [['name' => 'Bank', 'currency' => 'PLN', 'iban' => 'PL00 0000 0000 0000 0000 0000 0000']]
    );

    expect($snapshot['seller_snapshot_at'])->not->toBeNull()
        ->and($snapshot['seller_bank_accounts'])->toHaveCount(1)
        ->and($snapshot['seller_bank_accounts'][0]['iban'])->toContain('PL00');
});

it('prints the frozen seller in the KSeF XML, not the current settings', function () {
    $client = Client::factory()->create();
    $invoice = app(InvoiceService::class)->createInvoice($client, [
        ['description' => 'Hosting', 'amount' => 100],
    ]);

    pnlcs_test_seller_settings([
        'name' => 'New Seller',
        'address' => 'New Street 9',
        'tax_id' => '2222222222',
    ]);

    $xml = (new InvoiceXmlBuilder())->build($invoice->fresh(), '9999999999');

    expect($xml)->toContain('Old Seller')
        ->toContain('Old Street 1')
        ->toContain('1111111111')
        ->not->toContain('New Seller');
});
