<?php

use App\Models\AddonSetting;
use App\Models\Admin;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\KsefImportedInvoice;
use App\Models\KsefInvoice;
use Modules\Ksef\KsefClient;
use Modules\Ksef\KsefInvoiceImporter;

function ksefImportConfigured(): void
{
    AddonSetting::setSetting('ksef', 'nip', 'PL6482409327');
    AddonSetting::setSetting('ksef', 'private_key', 'test-key');
    AddonSetting::setSetting('ksef', 'certificate', 'test-cert');
}

it('imports sales and cost invoices from KSeF without duplicating them', function () {
    ksefImportConfigured();

    $client = Mockery::mock(KsefClient::class);
    $client->shouldReceive('authenticate')->twice()->andReturn('token');
    $client->shouldReceive('queryInvoiceMetadata')->times(4)->andReturnUsing(function ($token, $endpoint, $subject) {
        return ['invoices' => [[
            'ksefNumber' => $subject === 'Subject1' ? '1111111111-20260101-ABC-01' : '2222222222-20260101-DEF-02',
            'invoiceNumber' => $subject === 'Subject1' ? 'FV-1' : 'FK-2',
            'issueDate' => '2026-11-20',
            'seller' => ['nip' => $subject === 'Subject1' ? 'PL6482409327' : '5261040828', 'name' => 'Supplier'],
            'buyer' => ['nip' => $subject === 'Subject1' ? '5261040828' : 'PL6482409327', 'name' => 'Buyer'],
            'netAmount' => 100,
            'vatAmount' => 23,
            'grossAmount' => 123,
            'currency' => 'PLN',
        ]]];
    });
    $client->shouldReceive('downloadInvoice')->times(4)->andReturn('<Faktura><P_1>2026-11-20</P_1><P_2>FV-1</P_2></Faktura>');

    app()->instance(KsefClient::class, $client);

    $importer = app(KsefInvoiceImporter::class);
    $first = $importer->import();
    $second = $importer->import();

    expect($first['status'])->toBe('ok')
        ->and($first['created'])->toBe(2)
        ->and($second['updated'])->toBe(2)
        ->and(KsefImportedInvoice::count())->toBe(2)
        ->and(KsefImportedInvoice::where('direction', 'sales')->value('seller_nip'))->toBe('6482409327')
        ->and(KsefImportedInvoice::where('direction', 'cost')->value('buyer_nip'))->toBe('6482409327');
});

it('shows sent invoices by default and imported invoices in their tabs', function () {
    $admin = Admin::factory()->create();
    $client = Client::factory()->create();
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'total' => 10]);

    KsefInvoice::create([
        'invoice_id' => $invoice->id,
        'status' => 'accepted',
        'ksef_number' => '1111111111-20260101-ABC-01',
        'sent_at' => now(),
        'accepted_at' => now(),
    ]);

    KsefImportedInvoice::create([
        'ksef_number' => '3333333333-20260101-GHI-03',
        'direction' => 'sales',
        'invoice_number' => 'SALE-1',
        'issue_date' => now()->toDateString(),
        'seller_nip' => '6482409327',
        'buyer_name' => 'Sales buyer',
        'imported_at' => now(),
    ]);
    KsefImportedInvoice::create([
        'ksef_number' => '4444444444-20260101-JKL-04',
        'direction' => 'cost',
        'invoice_number' => 'COST-1',
        'issue_date' => now()->toDateString(),
        'seller_name' => 'Cost seller',
        'buyer_nip' => '6482409327',
        'imported_at' => now(),
    ]);

    $this->actingAs($admin, 'admin')
        ->get(route('admin.ksef.index'))
        ->assertOk()
        ->assertSee(__('messages.ksef.tab_sent'))
        ->assertDontSee('SALE-1')
        ->assertDontSee('COST-1');

    $this->actingAs($admin, 'admin')
        ->get(route('admin.ksef.index', ['tab' => 'sales', 'year' => now()->year, 'month' => now()->month]))
        ->assertOk()
        ->assertSee('SALE-1')
        ->assertDontSee('COST-1');

    $this->actingAs($admin, 'admin')
        ->get(route('admin.ksef.index', ['tab' => 'costs', 'year' => now()->year, 'month' => now()->month]))
        ->assertOk()
        ->assertSee('COST-1');
});
