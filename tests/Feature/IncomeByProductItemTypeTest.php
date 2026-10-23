<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Http\Request;
use Modules\Reports\IncomeByProductReport;

/**
 * Income by Product joined every invoice line to services on rel_id. Only
 * hosting and overage lines carry a service id there; a domain, addon or
 * upgrade line carries its own row's id, and when a service had the same
 * number the line's money was credited to that service's product.
 */
it('credits a product only with lines that belong to its services', function () {
    $client = Client::factory()->create();
    $product = Product::factory()->create(['name' => 'Income Alpha Plan']);
    $service = Service::factory()->create(['client_id' => $client->id, 'product_id' => $product->id]);

    $invoice = Invoice::factory()->create([
        'client_id' => $client->id, 'status' => 'paid', 'subtotal' => 115, 'tax' => 0, 'total' => 115,
        'date' => '2031-03-10', 'date_paid' => '2031-03-10 10:00:00',
    ]);
    foreach ([['Hosting', 100.0, 'Income Alpha Plan (monthly)'], ['Domain', 15.0, 'Domain Registration - income-alpha.test']] as [$type, $amount, $description]) {
        InvoiceItem::create([
            'invoice_id' => $invoice->id, 'client_id' => $client->id, 'type' => $type,
            // The domain line's own id happens to equal the service's.
            'rel_id' => $service->id, 'description' => $description, 'amount' => $amount, 'taxed' => false,
        ]);
    }

    $rows = collect((new IncomeByProductReport)->generate(Request::create('/', 'GET', ['from' => '2031-03-01', 'to' => '2031-03-31']))['rows']);

    expect((float) $rows->firstWhere('product', 'Income Alpha Plan')->revenue)->toEqual(100.0)
        ->and((float) $rows->firstWhere('product', 'Domain Registration - income-alpha.test')?->revenue)->toEqual(15.0)
        ->and((float) $rows->sum('revenue'))->toEqual(115.0);
});
