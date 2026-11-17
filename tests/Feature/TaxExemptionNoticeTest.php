<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Setting;
use App\Models\TaxRule;
use App\Models\User;
use App\Services\CartService;
use App\Services\InvoicePdfService;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\Mail;

/*
 * #449. A shop under a small-business VAT exemption (Austria's § 6 Abs. 1
 * Z 27 UStG, Germany's § 19 UStG) must say so on its invoices, and could only
 * do it by editing the cart and PDF views. The tax page now takes the line;
 * it is printed on documents that carry no tax and on none that do.
 */

const TEN_NOTICE = 'Umsatzsteuerfrei aufgrund der Kleinunternehmerregelung gemäß § 6 Abs. 1 Z 27 UStG 1994.';

beforeEach(fn () => Mail::fake());

function tenInvoice(?float $rate): Invoice
{
    $client = Client::factory()->create(['tax_exempt' => false, 'country' => 'AT']);
    if ($rate !== null) {
        TaxRule::create(['name' => 'USt', 'country' => 'AT', 'state' => '', 'tax_rate' => $rate, 'is_default' => true]);
    }

    return app(InvoiceService::class)->createInvoice($client, [['description' => 'Webhosting', 'amount' => 60.00, 'taxed' => true]]);
}

function tenPdf(Invoice $invoice): string
{
    $invoice->load('client', 'items');

    return view('pdf.invoice', ['invoice' => $invoice, 'company' => app(InvoicePdfService::class)->companyDetails(), 'ksef' => null])->render();
}

test('the tax page saves the notice', function () {
    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);

    $this->actingAs($admin, 'admin')
        ->post(route('admin.settings.general.update'), ['TaxEnabled' => '0', 'TaxExemptionNotice' => '  '.TEN_NOTICE.' '])
        ->assertSessionHasNoErrors();

    expect(Setting::get('TaxExemptionNotice'))->toContain(TEN_NOTICE);
    $this->actingAs($admin, 'admin')->get(route('admin.config.tax'))->assertOk()->assertSee(TEN_NOTICE);
});

test('an invoice without tax carries the notice on the PDF, the custom template and the customer\'s page', function () {
    Setting::set('TaxExemptionNotice', TEN_NOTICE);
    $invoice = tenInvoice(null);
    $user = User::factory()->create();
    $user->clients()->attach($invoice->client_id, ['owner' => true]);

    expect((float) $invoice->tax)->toBe(0.0)
        ->and(tenPdf($invoice))->toContain(e(TEN_NOTICE))
        ->and(app(InvoicePdfService::class)->placeholderVars($invoice)['tax_notice'])->toBe(e(TEN_NOTICE));

    $this->actingAs($user)->get(route('client.invoices.show', $invoice))->assertOk()->assertSee(TEN_NOTICE);
});

test('an invoice that charges tax does not', function () {
    Setting::set('TaxExemptionNotice', TEN_NOTICE);
    $invoice = tenInvoice(20);
    $user = User::factory()->create();
    $user->clients()->attach($invoice->client_id, ['owner' => true]);

    expect((float) $invoice->tax)->toBeGreaterThan(0.0)
        ->and(tenPdf($invoice))->not->toContain(e(TEN_NOTICE))
        ->and(app(InvoicePdfService::class)->placeholderVars($invoice)['tax_notice'])->toBe('');

    $this->actingAs($user)->get(route('client.invoices.show', $invoice))->assertOk()->assertDontSee(TEN_NOTICE);
});

test('with no notice set nothing is added', function () {
    $invoice = tenInvoice(null);

    expect(tenPdf($invoice))->not->toContain('Umsatzsteuerfrei')
        ->and(app(InvoicePdfService::class)->placeholderVars($invoice)['tax_notice'])->toBe('');
});

test('the cart and the checkout show the notice when no tax is added', function () {
    Setting::set('TaxExemptionNotice', TEN_NOTICE);
    Setting::set('TaxEnabled', '0');

    $currency = Currency::where('is_default', true)->first()
        ?? Currency::create(['code' => 'EUR', 'prefix' => '€', 'suffix' => '', 'rate' => 1, 'is_default' => true]);
    $client = Client::factory()->create(['tax_exempt' => false, 'country' => 'AT']);
    TaxRule::create(['name' => 'USt', 'country' => 'AT', 'state' => '', 'tax_rate' => 20, 'is_default' => true]);
    $product = Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id, 'tax' => true, 'server_type' => null]);
    Pricing::updateOrCreate(['type' => 'product', 'rel_id' => $product->id, 'currency_id' => $currency->id], ['monthly' => 6]);
    $user = User::factory()->create();
    $user->clients()->attach($client->id, ['owner' => true]);

    $carts = app(CartService::class);
    $carts->addProduct($carts->getOrCreateCart($client->id), $product, 'monthly', 'site.example.com');

    $this->actingAs($user)->get(route('client.cart.index'))->assertOk()->assertSee(TEN_NOTICE);
    $this->actingAs($user)->get(route('client.cart.checkout'))->assertOk()->assertSee(TEN_NOTICE);

    // Tax back on: the same cart now adds it, and the line goes.
    Setting::set('TaxEnabled', '1');
    $this->actingAs($user)->get(route('client.cart.index'))->assertOk()->assertDontSee(TEN_NOTICE);
});
