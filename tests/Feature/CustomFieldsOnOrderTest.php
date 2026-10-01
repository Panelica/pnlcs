<?php

use App\Models\Client;
use App\Models\Currency;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;

/*
 * Client custom fields flagged "show on order form" are asked at checkout.
 *
 * The admin screen saved the flag and nothing read it: a field an operator
 * needed from every new customer (a tax office, "how did you hear of us")
 * was only ever asked on the profile page, after the order.
 */

function cfoField(array $attrs = []): CustomField
{
    return CustomField::create(array_merge([
        'type' => 'client', 'rel_id' => 0, 'field_name' => 'How did you hear of us',
        'field_type' => 'text', 'required' => false, 'admin_only' => false,
        'show_on_order' => true, 'show_on_invoice' => false, 'sort_order' => 0,
    ], $attrs));
}

function cfoCart(): void
{
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'server_type' => '', 'type' => 'other', 'hidden' => false, 'retired' => false,
    ]);
    Pricing::create([
        'type' => 'product', 'rel_id' => $product->id, 'monthly' => 10,
        'currency_id' => Currency::getDefault()?->id ?? Currency::factory()->create()->id,
    ]);
    test()->post(route('client.cart.add'), ['product_id' => $product->id, 'billing_cycle' => 'monthly']);
}

function cfoCheckout(array $custom): TestResponse
{
    return test()->post(route('client.cart.process'), [
        'first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'ada-order@example.test',
        'password' => 'secret-enough', 'password_confirmation' => 'secret-enough',
        'address1' => '1 Test Street', 'city' => 'Istanbul', 'postcode' => '34000', 'country' => 'TR',
        'payment_method' => 'banktransfer', 'terms' => '1',
        'custom_fields' => $custom,
    ]);
}

it('asks a new customer for the fields flagged for the order form', function () {
    $asked = cfoField();
    cfoField(['field_name' => 'Internal reference', 'show_on_order' => false]);
    cfoField(['field_name' => 'Staff only', 'admin_only' => true]);
    cfoCart();

    $this->get(route('client.cart.checkout'))
        ->assertOk()
        ->assertSee($asked->field_name)
        ->assertDontSee('Internal reference')
        ->assertDontSee('Staff only');
});

it('saves the answers against the account opened at checkout', function () {
    Mail::fake();
    $field = cfoField();
    cfoCart();

    cfoCheckout([$field->id => 'A friend'])->assertSessionHasNoErrors();

    $client = User::where('email', 'ada-order@example.test')->first()->clients()->first();
    expect($field->valueFor($client->id))->toBe('A friend');
});

it('refuses the order when a required field is left empty', function () {
    Mail::fake();
    $field = cfoField(['required' => true]);
    cfoCart();

    cfoCheckout([$field->id => ''])->assertSessionHasErrors("custom_fields.{$field->id}");

    expect(User::where('email', 'ada-order@example.test')->exists())->toBeFalse();
});

it('does not ask a signed-in customer again', function () {
    $field = cfoField();
    $user = User::factory()->create();
    $user->clients()->attach(Client::factory()->create()->id);
    $this->actingAs($user);
    cfoCart();

    $this->get(route('client.cart.checkout'))->assertOk()->assertDontSee($field->field_name);
});

it('still saves the fields edited on the profile', function () {
    $field = cfoField(['show_on_order' => false]);
    $user = User::factory()->create();
    $client = Client::factory()->create(['country' => 'TR']);
    $user->clients()->attach($client->id);

    $this->actingAs($user)->put(route('client.account.update'), [
        'first_name' => $client->first_name, 'last_name' => $client->last_name, 'email' => $user->email,
        'country' => 'TR', 'address1' => '1 Test Street', 'city' => 'Istanbul', 'postcode' => '34000',
        'custom_fields' => [$field->id => 'Kept'],
    ]);

    expect(CustomFieldValue::where('field_id', $field->id)->where('rel_id', $client->id)->value('value'))->toBe('Kept');
});
