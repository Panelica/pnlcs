<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Promotion;
use App\Models\Service;
use App\Services\InvoiceGenerationService;

/**
 * The promotions form stores a fixed discount as type 'fixed_amount', but the
 * invoice only knew 'fixed': the cart quoted the discount and the invoice
 * charged the full price.
 */
function fixedAmountInvoice(float $amount): Invoice
{
    $product = Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id]);
    $client = Client::factory()->create();
    $service = Service::factory()->create(['client_id' => $client->id, 'product_id' => $product->id, 'status' => 'active']);

    $invoice = Invoice::factory()->create([
        'client_id' => $client->id, 'status' => 'unpaid',
        'subtotal' => $amount, 'total' => $amount, 'due_date' => now()->addDays(14),
    ]);
    InvoiceItem::create(['invoice_id' => $invoice->id, 'client_id' => $client->id, 'type' => 'Hosting', 'rel_id' => $service->id, 'description' => 'Plan', 'amount' => $amount, 'taxed' => false]);

    return $invoice;
}

it('takes a fixed_amount code off the invoice', function () {
    $invoice = fixedAmountInvoice(50);
    Promotion::create(['code' => 'TEN-OFF', 'type' => 'fixed_amount', 'value' => 10]);

    $applied = app(InvoiceGenerationService::class)->applyPromotion($invoice, 'TEN-OFF');

    expect($applied)->toBeTrue()
        ->and((float) $invoice->fresh()->items()->where('type', 'Discount')->value('amount'))->toBe(-10.0)
        ->and((float) $invoice->fresh()->total)->toBe(40.0);
});

it('caps a fixed_amount code at the invoice amount', function () {
    $invoice = fixedAmountInvoice(5);
    Promotion::create(['code' => 'TWENTY-OFF', 'type' => 'fixed_amount', 'value' => 20]);

    app(InvoiceGenerationService::class)->applyPromotion($invoice, 'TWENTY-OFF');

    expect((float) $invoice->fresh()->items()->where('type', 'Discount')->value('amount'))->toBe(-5.0);
});

it('accepts fixed_amount from the promotions form', function () {
    $this->actingAs(\App\Models\Admin::factory()->create(), 'admin');

    $this->post(route('admin.config.promotions.store'), [
        'code' => 'FORM-OFF', 'type' => 'fixed_amount', 'value' => 7,
    ])->assertSessionHasNoErrors();

    expect(Promotion::where('code', 'FORM-OFF')->value('type'))->toBe('fixed_amount');
});
