<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Affiliate;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Services\PaymentService;

/*
 * A separate commission for renewals.
 *
 * An affiliate earned one rate on every invoice of the customer they brought
 * in, for as long as the customer stayed - or, with "one-time", only once.
 * Most programmes pay more on the first sale and less (or nothing) on the
 * renewals that follow.
 */

function arrSetup(?float $renewalRate, string $type = 'percentage', float $rate = 20): array
{
    $partner = Client::factory()->create();
    $affiliate = Affiliate::create(['client_id' => $partner->id, 'pay_type' => $type, 'pay_amount' => $rate, 'recurring_pay_amount' => $renewalRate,
        'onetime' => false, 'visitors' => 0, 'balance' => 0, 'withdrawn' => 0]);
    $customer = Client::factory()->create(['affiliate_id' => $affiliate->id]);

    return [$affiliate, $customer];
}

function arrInvoice(Client $customer, bool $fromOrder, float $amount = 100): Invoice
{
    $invoice = Invoice::factory()->create(['client_id' => $customer->id, 'status' => 'unpaid', 'subtotal' => $amount, 'total' => $amount, 'credit' => 0, 'tax' => 0]);
    InvoiceItem::create(['invoice_id' => $invoice->id, 'client_id' => $customer->id, 'type' => 'Hosting', 'description' => 'Hosting', 'amount' => $amount]);
    if ($fromOrder) {
        Order::create(['order_num' => (string) random_int(1000000, 9999999), 'client_id' => $customer->id, 'date' => now(), 'amount' => $amount, 'status' => 'Pending', 'invoice_id' => $invoice->id]);
    }
    app(PaymentService::class)->applyPayment($invoice, 'banktransfer', 'tx-'.uniqid(), null);

    return $invoice;
}

it('pays the main rate on a new sale and the renewal rate on a renewal', function () {
    [$affiliate, $customer] = arrSetup(5);

    arrInvoice($customer, true);
    expect((float) $affiliate->fresh()->balance)->toBe(20.0);

    arrInvoice($customer, false);
    expect((float) $affiliate->fresh()->balance)->toBe(25.0);
});

it('pays the same on everything when no renewal rate is set', function () {
    [$affiliate, $customer] = arrSetup(null);

    arrInvoice($customer, true);
    arrInvoice($customer, false);

    expect((float) $affiliate->fresh()->balance)->toBe(40.0);
});

it('pays nothing on renewals at a renewal rate of zero', function () {
    [$affiliate, $customer] = arrSetup(0);

    arrInvoice($customer, true);
    arrInvoice($customer, false);

    expect((float) $affiliate->fresh()->balance)->toBe(20.0);
});

it('uses the renewal amount for a flat commission too', function () {
    [$affiliate, $customer] = arrSetup(2, 'flat', 15);

    arrInvoice($customer, true);
    arrInvoice($customer, false);

    expect((float) $affiliate->fresh()->balance)->toBe(17.0);
});

it('is set and cleared from the affiliate\'s admin page', function () {
    [$affiliate] = arrSetup(null);
    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->create(['name' => 'A', 'permissions' => ['manage_affiliates']])->id]);

    test()->actingAs($admin, 'admin')->put(route('admin.affiliates.update', $affiliate), ['pay_type' => 'percentage', 'pay_amount' => 20, 'recurring_pay_amount' => 5])->assertSessionHasNoErrors();
    expect((float) $affiliate->fresh()->recurring_pay_amount)->toBe(5.0);

    test()->actingAs($admin, 'admin')->put(route('admin.affiliates.update', $affiliate), ['pay_type' => 'percentage', 'pay_amount' => 20, 'recurring_pay_amount' => ''])->assertSessionHasNoErrors();
    expect($affiliate->fresh()->recurring_pay_amount)->toBeNull();
});
