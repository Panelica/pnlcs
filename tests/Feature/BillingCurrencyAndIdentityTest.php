<?php

use App\Http\Controllers\Client\CartController;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Setting;
use App\Models\User;
use App\Support\BillingIdentity;
use Illuminate\Support\Facades\Http;

/*
 * Billing in a second currency, and knowing who the buyer is.
 *
 * A host that prices in one currency and collects in another learned two
 * things the hard way: an invoice has to remember the rate it was struck at,
 * or a document reprinted next year shows a different figure from the one the
 * customer agreed to; and switching the shop currency silently reinterpreted
 * every past invoice at the new sign until the currency was written down
 * beside the number.
 */

function shopCurrency(string $code, float $rate = 1.0, bool $default = false): Currency
{
    return Currency::updateOrCreate(['code' => $code], ['name' => $code, 'prefix' => $code.' ', 'rate' => $rate, 'is_default' => $default]);
}

function billedClient(array $extra = []): Client
{
    return Client::factory()->create(array_merge([
        'country' => 'TR', 'address1' => '1 Test Street', 'city' => 'Istanbul', 'postcode' => '34000',
    ], $extra));
}

// ------------------------------------------------------ currency stamping

test('an invoice remembers the currency its amounts are in', function () {
    shopCurrency('USD', 1.0, true);
    $invoice = Invoice::factory()->create(['client_id' => billedClient()->id, 'total' => 100]);

    expect($invoice->source_currency)->toBe(strtoupper(Currency::getDefault()->code));
});

test('with a billing currency configured the rate is frozen on the invoice', function () {
    shopCurrency('USD', 1.0, true);
    shopCurrency('TRY', 40.0);
    Setting::set('BillingCurrency', 'TRY');
    Setting::set('ExchangeRateSource', 'TCMB');
    Setting::set('ExchangeRateDate', '09.09.2026');

    $invoice = Invoice::factory()->create(['client_id' => billedClient()->id, 'total' => 10]);

    expect($invoice->billing_currency)->toBe('TRY')
        ->and((float) $invoice->exchange_rate)->toBe(40.0)
        ->and($invoice->exchange_rate_source)->toBe('TCMB')
        ->and(billing_money_fmt(10, $invoice))->toContain('400.00')
        ->and(billing_rate_note($invoice))->toContain('TCMB');

    // The rate moves afterwards; the invoice does not.
    Currency::where('code', 'TRY')->update(['rate' => 45]);
    expect(billing_money_fmt(10, $invoice->fresh()))->toContain('400.00');
});

test('without a billing currency nothing changes for anyone', function () {
    Setting::set('BillingCurrency', '');
    $invoice = Invoice::factory()->create(['client_id' => billedClient()->id, 'total' => 10]);

    expect($invoice->billing_currency)->toBeNull()
        ->and(billing_rate_note($invoice))->toBeNull()
        ->and(dual_money_fmt(10, $invoice))->toBe(invoice_money_fmt(10, $invoice));
});

test('the payment reference is short and its prefix is the operators', function () {
    $invoice = Invoice::factory()->create(['client_id' => billedClient()->id, 'total' => 10]);

    expect(payment_ref($invoice))->toBe('INV'.$invoice->id);

    Setting::set('PaymentReferencePrefix', 'EN');
    expect(payment_ref($invoice))->toBe('EN'.$invoice->id);
});

// ------------------------------------------------------- official rate

test('the official rate source only speaks when an operator chose it', function () {
    shopCurrency('USD', 1.0, true);
    shopCurrency('TRY', 30.0);
    Setting::set('CurrencyUpdateEnabled', '1');
    Setting::set('OfficialRateProvider', '');

    Http::fake([
        'open.er-api.com/*' => Http::response(['result' => 'success', 'rates' => ['TRY' => 33.0]], 200),
        'api.frankfurter.app/*' => Http::response(['rates' => ['TRY' => 33.0]], 200),
        'www.tcmb.gov.tr/*' => Http::response('<Tarih_Date Tarih="09.09.2026" Bulten_No="2026/170"><Currency Kod="USD"><Unit>1</Unit><ForexSelling>41,2000</ForexSelling></Currency></Tarih_Date>', 200),
    ]);

    $this->artisan('pnlcs:currency-update');
    expect((float) Currency::where('code', 'TRY')->value('rate'))->toBe(33.0);

    Setting::set('OfficialRateProvider', 'tcmb');
    $this->artisan('pnlcs:currency-update');

    expect((float) Currency::where('code', 'TRY')->value('rate'))->toBe(41.2)
        ->and(Setting::get('ExchangeRateSource'))->toBe('TCMB')
        ->and(Setting::get('ExchangeRateBulletin'))->toBe('2026/170');
});

// -------------------------------------------------------- billing identity

test('what an invoice needs from the buyer depends on where the seller is', function () {
    Setting::set('Country', 'DE');
    expect(BillingIdentity::required('company'))->toContain('tax_id')
        ->and(BillingIdentity::required('company'))->not->toContain('tax_office')
        ->and(BillingIdentity::required('individual'))->not->toContain('national_id');

    Setting::set('Country', 'TR');
    expect(BillingIdentity::required('company'))->toContain('tax_office')
        ->and(BillingIdentity::required('individual'))->toContain('national_id')
        ->and(BillingIdentity::required(null))->toContain('client_type');
});

test('a client knows which of its own billing fields are missing', function () {
    Setting::set('Country', 'TR');
    $client = billedClient(['client_type' => 'company', 'company_name' => 'Acme', 'tax_office' => '', 'tax_id' => '123', 'phone_number' => '5551112233']);

    expect($client->missingBillingIdentity())->toBe(['tax_office'])
        ->and($client->hasBillingIdentity())->toBeFalse();

    $client->update(['tax_office' => 'Kadikoy']);
    expect($client->fresh()->hasBillingIdentity())->toBeTrue();
});

test('a turkish seller asks the buyer to identify themselves at checkout', function () {
    Setting::set('Country', 'TR');
    $product = Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id, 'server_type' => '', 'type' => 'other', 'hidden' => false, 'retired' => false]);
    Pricing::create(['type' => 'product', 'currency_id' => Currency::getDefault()?->id ?? Currency::factory()->create()->id, 'rel_id' => $product->id, 'monthly' => 10]);

    $this->post(route('client.cart.add'), ['product_id' => $product->id, 'billing_cycle' => 'monthly']);

    $html = $this->get(route('client.cart.checkout'))->assertOk()->getContent();
    expect($html)->toContain('name="client_type"')->and($html)->toContain('name="national_id"');

    // A company without a tax office is refused; a person without an ID too.
    $this->post(route('client.cart.process'), [
        'first_name' => 'A', 'last_name' => 'B', 'email' => 'tr-buyer@example.test',
        'password' => 'secret-enough', 'password_confirmation' => 'secret-enough',
        'address1' => 'x', 'city' => 'y', 'postcode' => 'z', 'country' => 'TR', 'phone_number' => '5551112233',
        'client_type' => 'company', 'company_name' => 'Acme', 'tax_id' => '1',
        'payment_method' => 'banktransfer', 'terms' => '1',
    ])->assertSessionHasErrors('tax_office');
});

test('a seller elsewhere is not asked for turkish identity numbers', function () {
    Setting::set('Country', 'DE');
    $product = Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id, 'server_type' => '', 'type' => 'other', 'hidden' => false, 'retired' => false]);
    Pricing::create(['type' => 'product', 'currency_id' => Currency::getDefault()?->id ?? Currency::factory()->create()->id, 'rel_id' => $product->id, 'monthly' => 10]);
    $this->post(route('client.cart.add'), ['product_id' => $product->id, 'billing_cycle' => 'monthly']);

    expect($this->get(route('client.cart.checkout'))->assertOk()->getContent())->not->toContain('name="national_id"');
});

test('the admin can see who cannot be invoiced yet, and export the list', function () {
    Setting::set('Country', 'TR');
    billedClient(['client_type' => 'individual', 'national_id' => '', 'phone_number' => '5551112233', 'email' => 'gap@example.test']);
    billedClient(['client_type' => 'individual', 'national_id' => '11111111111', 'phone_number' => '5551112233', 'email' => 'ok@example.test']);

    $admin = Admin::factory()->create([
        'role_id' => AdminRole::factory()->create(['name' => 'Clients', 'permissions' => ['list_clients', 'view_clients']])->id,
    ]);

    $this->actingAs($admin, 'admin')->get(route('admin.clients.billing', ['only_missing' => 1]))
        ->assertOk()
        ->assertSee('gap@example.test')
        ->assertDontSee('ok@example.test');

    $csv = $this->actingAs($admin, 'admin')->get(route('admin.clients.billing.csv'))->assertOk()->streamedContent();
    expect($csv)->toContain('gap@example.test')->and($csv)->toContain('ok@example.test');
});

test('the admin invoice list has a tab for invoices awaiting approval', function () {
    $admin = Admin::factory()->create([
        'role_id' => AdminRole::factory()->create(['name' => 'Inv', 'permissions' => ['list_invoices']])->id,
    ]);

    $this->actingAs($admin, 'admin')->get(route('admin.invoices.index'))
        ->assertOk()
        ->assertSee(__('admin.invoices.filter_payment_pending'));
});

// ---------------------------------------------------------- adding funds

test('a top-up is entered in the billing currency and lands on the balance in the shop currency', function () {
    shopCurrency('USD', 1.0, true);
    shopCurrency('TRY', 40.0);
    Setting::set('BillingCurrency', 'TRY');
    \App\Models\GatewaySettings::updateOrCreate(['gateway' => 'banktransfer', 'setting' => 'active'], ['value' => '1']);

    $client = billedClient();
    $user = User::factory()->create();
    $user->clients()->attach($client->id);

    // Limits and presets follow the rate: 5 USD is 200 TRY today, not 5 TRY.
    $html = $this->actingAs($user)->get(route('client.funds.index'))->assertOk()->getContent();
    expect($html)->toContain('data-rate="40"')->and($html)->toContain('min="200"');

    $this->actingAs($user)->post(route('client.funds.store'), ['amount' => 4000, 'payment_method' => 'banktransfer'])
        ->assertSessionHasNoErrors();

    $invoice = Invoice::where('client_id', $client->id)->latest('id')->first();
    expect((float) $invoice->total)->toBe(100.0)
        ->and($invoice->billing_currency)->toBe('TRY')
        ->and(billing_money_fmt(100, $invoice))->toContain('4,000.00');
});

// ---------------------------------------------------------- bank transfer

test('a host can list up to three banks and hide one without deleting it', function () {
    $set = fn (string $k, string $v) => \App\Models\GatewaySettings::updateOrCreate(['gateway' => 'banktransfer', 'setting' => $k], ['value' => $v]);
    $set('account_name', 'Example Hosting Ltd');
    $set('bank_name', 'First Bank');   $set('iban', 'TR000000000000000000000001');
    $set('bank2_name', 'Second Bank'); $set('bank2_account_number', '12345678'); $set('bank2_sort_code', '00-11-22');
    $set('bank3_name', 'Hidden Bank'); $set('bank3_iban', 'TR000000000000000000000003'); $set('bank3_active', '0');

    $form = app(\Modules\Gateways\BankTransfer\BankTransferModule::class)
        ->getPaymentForm(Invoice::factory()->create(['client_id' => billedClient()->id, 'total' => 10]));

    expect($form)->toContain('First Bank')->toContain('TR000000000000000000000001')
        ->toContain('Second Bank')->toContain('12345678')->toContain('00-11-22')
        ->not->toContain('Hidden Bank')
        ->and(substr_count($form, 'Example Hosting Ltd'))->toBe(2);
});
