<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Domain;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Models\ServiceAddon;
use App\Models\Setting;
use App\Services\InvoiceGenerationService;
use Illuminate\Support\Facades\Mail;

/*
 * #302. Everything a client has falling due in the same run goes on one
 * renewal invoice, so a customer with two servers who wants to keep one and
 * let the other go can only pay for both or neither. InvoiceRenewalsSeparately
 * gives each renewal its own invoice; off, as by default, nothing changes.
 */

beforeEach(fn () => Mail::fake());

function sriSetup(): array
{
    $client = Client::factory()->create(['tax_exempt' => true]);
    $product = Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id, 'tax' => false]);
    $make = fn ($due) => Service::factory()->create(['client_id' => $client->id, 'product_id' => $product->id, 'status' => 'active',
        'auto_renew' => true, 'billing_cycle' => 'Monthly', 'amount' => 10, 'next_due_date' => $due]);
    $a = $make(now()->addDays(3)->toDateString());
    $b = $make(now()->addDays(4)->toDateString());
    $domain = Domain::factory()->create(['client_id' => $client->id, 'next_due_date' => now()->addDays(5), 'recurring_amount' => 12, 'payment_method' => 'banktransfer']);

    return [$client, $a, $b, $domain];
}

function sriLines(Client $client): array
{
    return Invoice::where('client_id', $client->id)->orderBy('id')->get()
        ->map(fn ($i) => InvoiceItem::where('invoice_id', $i->id)->orderBy('id')->get()->map(fn ($it) => $it->type.'#'.$it->rel_id)->implode(','))
        ->all();
}

test('off, as by default, the client gets one renewal invoice', function () {
    [$client] = sriSetup();

    app(InvoiceGenerationService::class)->generateDueInvoices();

    expect(Invoice::where('client_id', $client->id)->count())->toBe(1);
});

test('on, each service and each domain is invoiced on its own, and a service takes its addons with it', function () {
    Setting::set('InvoiceRenewalsSeparately', '1');
    [$client, $a, $b, $domain] = sriSetup();
    $addon = ServiceAddon::create(['service_id' => $a->id, 'client_id' => $client->id, 'status' => 'active', 'amount' => 2, 'billing_cycle' => 'Monthly', 'next_due_date' => now()->addDays(3)]);

    app(InvoiceGenerationService::class)->generateDueInvoices();
    $lines = sriLines($client);

    expect($lines)->toHaveCount(3)
        ->and($lines)->toContain("Hosting#{$b->id}")
        ->and($lines)->toContain("Domain#{$domain->id}")
        ->and(collect($lines)->first(fn ($l) => str_contains($l, "Hosting#{$a->id}")))->toContain("Addon#{$addon->id}");

    // Paying one leaves the other open; nothing is invoiced twice on the next run.
    app(InvoiceGenerationService::class)->generateDueInvoices();
    expect(Invoice::where('client_id', $client->id)->count())->toBe(3);
});

test('the switch is saved from General Settings', function () {
    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);

    $this->actingAs($admin, 'admin')->post(route('admin.settings.general.update'), ['InvoiceRenewalsSeparately' => '1'])->assertSessionHasNoErrors();
    expect(Setting::get('InvoiceRenewalsSeparately'))->toBe('1');
    $this->actingAs($admin, 'admin')->get(route('admin.settings.general'))->assertOk()
        ->assertSee('name="InvoiceRenewalsSeparately"', false)->assertSee(__('admin.settings.invoice_renewals_separately'));
});
