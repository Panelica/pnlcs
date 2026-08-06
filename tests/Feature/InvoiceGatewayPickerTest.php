<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\GatewaySettings;
use App\Models\PaymentMethod;

/**
 * Somebody else's bank account, on a screen about somebody else.
 *
 * The payment-method box on the new-invoice form is a gateway picker: what it
 * stores is a gateway name, and the invoice is paid through it. It was filled
 * from the payment_methods table - every customer's stored method, unscoped -
 * using each customer's own description as the label.
 *
 * So an operator raising an invoice for one customer is shown the payment
 * methods other customers have named, and the list is wrong besides: three
 * customers with a bank account give three identical options, and an
 * installation where nobody has saved one offers no gateway at all.
 */
function invoiceCreateScreen(): string
{
    $role = AdminRole::factory()->create(['is_full_admin' => false, 'permissions' => ['create_invoices', 'list_invoices']]);
    $admin = Admin::factory()->create(['role_id' => $role->id]);

    return test()->actingAs($admin, 'admin')
        ->get(route('admin.invoices.create'))
        ->assertOk()
        ->getContent();
}

it('does not show one customer the payment methods of another', function () {
    PaymentMethod::create([
        'client_id' => Client::factory()->create()->id,
        'description' => 'Yilmaz family current account',
        'gateway_name' => 'banktransfer',
        'payment_type' => 'BankAccount',
        'last_four' => '4321',
    ]);

    expect(invoiceCreateScreen())->not->toContain('Yilmaz family current account');
});

it('offers the gateways this installation actually has', function () {
    GatewaySettings::updateOrCreate(['gateway' => 'stripe', 'setting' => 'active'], ['value' => '1']);
    GatewaySettings::updateOrCreate(['gateway' => 'stripe', 'setting' => 'secret_key'], ['value' => 'sk_test']);
    GatewaySettings::updateOrCreate(['gateway' => 'stripe', 'setting' => 'publishable_key'], ['value' => 'pk_test']);

    expect(invoiceCreateScreen())->toContain('value="stripe"');
});

it('still offers bank transfer and a manual entry', function () {
    $html = invoiceCreateScreen();

    expect($html)->toContain('value="banktransfer"');
    expect($html)->toContain('value="manual"');
});
