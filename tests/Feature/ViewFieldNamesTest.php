<?php

use App\Mail\AffiliateWelcomeMail;
use App\Mail\InvoicePaidMail;
use App\Models\Affiliate;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\User;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

/**
 * Views reading attributes the models do not have.
 *
 * Each one rendered its default quietly. The order form gated its whole domain
 * section on require_domain while the column is show_domain_options, so every
 * product here is marked to ask for a domain and not one customer was ever
 * asked — the five most recent services have no domain on them at all. The
 * affiliate welcome email reads commission_rate where the column is
 * pay_amount, so every new affiliate is told they earn 0%.
 */
function domainAskingProduct(bool $asks = true): Product
{
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'hidden' => false,
        'retired' => false,
        'show_domain_options' => $asks,
    ]);

    Pricing::create([
        'type' => 'product',
        'rel_id' => $product->id,
        'currency_id' => Currency::firstOrCreate(
            ['code' => 'USD'],
            ['prefix' => '$', 'suffix' => '', 'rate' => 1, 'is_default' => true]
        )->id,
        'monthly' => 10,
    ]);

    return $product;
}

function domainPromptShopper(): User
{
    $user = User::factory()->create();
    $user->clients()->attach(Client::factory()->create()->id);

    return $user;
}

test('a product that wants a domain asks for one', function () {
    $product = domainAskingProduct();

    $this->actingAs(domainPromptShopper())->get(route('client.store.configure', $product->slug))
        ->assertOk()
        ->assertSee('name="domain"', false)
        ->assertSee('name="domain_option"', false);
});

test('a product that does not want a domain does not ask', function () {
    $product = domainAskingProduct(false);

    $this->actingAs(domainPromptShopper())->get(route('client.store.configure', $product->slug))
        ->assertOk()
        ->assertDontSee('name="domain_option"', false);
});

test('a new affiliate is told what they actually earn', function () {
    $bodies = new ArrayObject;
    Event::listen(MessageSent::class, function ($event) use ($bodies) {
        $bodies->append(quoted_printable_decode($event->message->getBody()->bodyToString()));
    });

    $client = Client::factory()->create();
    $affiliate = Affiliate::create([
        'client_id' => $client->id,
        'visitors' => 0,
        'pay_type' => 'percentage',
        'pay_amount' => 15,
        'onetime' => false,
        'balance' => 0,
        'withdrawn' => 0,
    ]);

    Mail::to($client->email)->send(new AffiliateWelcomeMail($client, $affiliate));

    $body = implode(' ', $bodies->getArrayCopy());

    // 100% appears in the table markup, so the check is for the figure itself.
    expect($body)->toContain('>15%<');
});

test('a payment confirmation dates the payment, not the email', function () {
    $bodies = new ArrayObject;
    Event::listen(MessageSent::class, function ($event) use ($bodies) {
        $bodies->append(quoted_printable_decode($event->message->getBody()->bodyToString()));
    });

    $client = Client::factory()->create();
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'status' => 'paid',
        'total' => 30,
        'date_paid' => now()->subDays(9),
    ]);

    Mail::to($client->email)->send(new InvoicePaidMail($invoice));

    expect(implode(' ', $bodies->getArrayCopy()))
        ->toContain(now()->subDays(9)->format(date_fmt()));
});
