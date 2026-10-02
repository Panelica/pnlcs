<?php

use App\Models\Client;
use App\Models\ConfigOption;
use App\Models\ConfigOptionGroup;
use App\Models\ConfigOptionLink;
use App\Models\ConfigOptionSub;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Models\ServiceConfigOption;
use App\Models\Upgrade;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\ProvisioningService;
use Modules\Servers\Proxmox\ProxmoxPlan;

/*
 * Raising a service's options (more RAM, a bigger disk) on the same product.
 *
 * Options could only be picked at order time; afterwards the only move was to
 * another product.
 */

function uco(float $amount = 25): array
{
    $currency = Currency::where('is_default', true)->first()
        ?? Currency::create(['code' => 'USD', 'prefix' => '$', 'suffix' => '', 'rate' => 1, 'is_default' => true]);
    $product = Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id, 'name' => 'VPS M', 'server_type' => null, 'tax' => false]);
    Pricing::updateOrCreate(['type' => 'product', 'rel_id' => $product->id, 'currency_id' => $currency->id], ['monthly' => 20]);

    $group = ConfigOptionGroup::create(['name' => 'Resources']);
    ConfigOptionLink::create(['group_id' => $group->id, 'product_id' => $product->id]);
    $ram = ConfigOption::create(['group_id' => $group->id, 'option_name' => 'memory|RAM', 'option_type' => 'dropdown', 'sort_order' => 1]);
    $ram4 = ConfigOptionSub::create(['config_id' => $ram->id, 'option_name' => '4096|4 GB', 'sort_order' => 1]);
    $ram8 = ConfigOptionSub::create(['config_id' => $ram->id, 'option_name' => '8192|8 GB', 'sort_order' => 2]);
    $disk = ConfigOption::create(['group_id' => $group->id, 'option_name' => 'disk|Extra disk (10 GB)', 'option_type' => 'quantity', 'qty_minimum' => 0, 'qty_maximum' => 10, 'sort_order' => 2]);
    $diskUnit = ConfigOptionSub::create(['config_id' => $disk->id, 'option_name' => '10 GB', 'sort_order' => 1]);
    foreach ([[$ram4, 5], [$ram8, 15], [$diskUnit, 2]] as [$sub, $price]) {
        Pricing::updateOrCreate(['type' => ConfigOptionSub::PRICING_TYPE, 'rel_id' => $sub->id, 'currency_id' => $currency->id], ['monthly' => $price]);
    }

    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id, ['owner' => true]);
    $service = Service::factory()->create([
        'client_id' => $client->id, 'product_id' => $product->id, 'status' => 'active', 'amount' => $amount,
        'billing_cycle' => 'Monthly', 'next_due_date' => now()->addDays(15), 'domain' => 'vps.example',
    ]);
    ServiceConfigOption::create(['service_id' => $service->id, 'config_id' => $ram->id, 'option_id' => $ram4->id, 'qty' => 1, 'unit_price' => 5]);

    return compact('user', 'client', 'service', 'ram', 'ram4', 'ram8', 'disk');
}

it('shows the options on the upgrade page with the current choice', function () {
    $fx = uco();

    test()->actingAs($fx['user'])->get(route('client.services.upgrade', $fx['service']))
        ->assertOk()->assertSee(route('client.services.options.upgrade', $fx['service']), false)
        ->assertSee('value="'.$fx['ram4']->id.'" selected', false);
});

it('charges the difference for the days left and applies it once paid', function () {
    $fx = uco();
    $spy = Mockery::mock(ProvisioningService::class);
    $spy->shouldReceive('changePackage')->once()->andReturn(['success' => true]);
    app()->instance(ProvisioningService::class, $spy);

    test()->actingAs($fx['user'])->post(route('client.services.options.upgrade', $fx['service']), [
        'config_options' => [$fx['ram']->id => $fx['ram8']->id, $fx['disk']->id => 2],
    ])->assertRedirect();

    $invoice = Invoice::where('client_id', $fx['client']->id)->latest('id')->first();
    $line = $invoice->items()->where('type', 'Upgrade')->first();
    // +10 RAM, +4 disk = +14/month, for 15 of 30 days.
    expect((float) $line->amount)->toBe(7.0)
        ->and(Upgrade::where('type', 'configoptions')->value('status'))->toBe('pending')
        ->and((float) $fx['service']->fresh()->amount)->toBe(25.0);

    app(PaymentService::class)->applyPayment($invoice, 'banktransfer', 'tx-opt-1', null);

    $service = $fx['service']->fresh();
    expect((float) $service->amount)->toBe(39.0)
        ->and(ServiceConfigOption::where('service_id', $service->id)->where('config_id', $fx['ram']->id)->value('option_id'))->toBe($fx['ram8']->id)
        ->and((int) ServiceConfigOption::where('service_id', $service->id)->where('config_id', $fx['disk']->id)->value('qty'))->toBe(2)
        ->and(Upgrade::where('type', 'configoptions')->value('status'))->toBe('completed');

    // What the Proxmox module builds the guest from now carries the new RAM.
    expect(ProxmoxPlan::forService($service, [])->memory)->toBe(8192);
});

it('keeps a custom price and only adds the options\' difference', function () {
    $fx = uco(18);
    app()->instance(ProvisioningService::class, Mockery::mock(ProvisioningService::class, fn ($m) => $m->shouldReceive('changePackage')->andReturn(['success' => true])));

    test()->actingAs($fx['user'])->post(route('client.services.options.upgrade', $fx['service']), ['config_options' => [$fx['ram']->id => $fx['ram8']->id]]);
    app(PaymentService::class)->applyPayment(Invoice::where('client_id', $fx['client']->id)->latest('id')->first(), 'banktransfer', 'tx-opt-2', null);

    expect((float) $fx['service']->fresh()->amount)->toBe(28.0);
});

it('refuses to lower an option, or a change that changes nothing', function () {
    $fx = uco();
    ServiceConfigOption::where('service_id', $fx['service']->id)->update(['option_id' => $fx['ram8']->id, 'unit_price' => 15]);

    test()->actingAs($fx['user'])->post(route('client.services.options.upgrade', $fx['service']), ['config_options' => [$fx['ram']->id => $fx['ram4']->id]])
        ->assertSessionHas('error', __('client.services.options_only_raise'));
    test()->actingAs($fx['user'])->post(route('client.services.options.upgrade', $fx['service']), ['config_options' => [$fx['ram']->id => $fx['ram8']->id]])
        ->assertSessionHas('error', __('client.services.options_unchanged'));

    expect(Upgrade::count())->toBe(0);
});

it('takes one option change at a time', function () {
    $fx = uco();
    test()->actingAs($fx['user'])->post(route('client.services.options.upgrade', $fx['service']), ['config_options' => [$fx['ram']->id => $fx['ram8']->id]]);
    test()->actingAs($fx['user'])->post(route('client.services.options.upgrade', $fx['service']), ['config_options' => [$fx['ram']->id => $fx['ram8']->id, $fx['disk']->id => 1]])
        ->assertSessionHas('error', __('messages.error.upgrade_already_pending'));
});

it('does not let another customer change them', function () {
    $fx = uco();
    $stranger = User::factory()->create();
    $stranger->clients()->attach(Client::factory()->create()->id, ['owner' => true]);

    test()->actingAs($stranger)->post(route('client.services.options.upgrade', $fx['service']), ['config_options' => [$fx['ram']->id => $fx['ram8']->id]])->assertForbidden();
});
