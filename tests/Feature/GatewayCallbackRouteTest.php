<?php

use App\Contracts\GatewayModuleInterface;
use App\Models\Client;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use App\Services\Module\ModuleRegistry;
use Illuminate\Http\Request;

/*
 * A gateway installed from a pnlcs.json module (PayTR, Param, Papara...)
 * could be found and offered at checkout, but its provider had nowhere to say
 * the customer had paid: every webhook route was written in by hand for one
 * built-in gateway.
 */

function gcrGateway(string $name = 'fakepay', bool $active = true): ArrayObject
{
    $seen = new ArrayObject;
    $fake = Mockery::mock(GatewayModuleInterface::class);
    $fake->shouldReceive('getConfigFields')->andReturn([['name' => 'merchant_key', 'required' => true]]);
    $fake->shouldReceive('getModuleName')->andReturn($name);
    $fake->shouldReceive('processWebhook')->andReturnUsing(function (array $data) use ($seen) {
        $seen[] = $data;
        if (($data['_headers']['x-fakepay-signature'] ?? '') !== 'good') {
            return ['success' => false, 'response' => 'BAD', 'http_status' => 400];
        }

        return ['success' => true, 'invoice_id' => (int) $data['merchant_oid'], 'transaction_id' => 'fp-'.$data['merchant_oid'], 'amount' => (float) $data['total'], 'response' => 'OK'];
    });
    app()->instance(GatewayModuleInterface::class, $fake);
    app(ModuleRegistry::class)->registerGateway($name, GatewayModuleInterface::class);
    GatewaySettings::updateOrCreate(['gateway' => $name, 'setting' => 'active'], ['value' => $active ? '1' : '0']);
    GatewaySettings::updateOrCreate(['gateway' => $name, 'setting' => 'merchant_key'], ['value' => 'k']);

    return $seen;
}

test('a gateway with no route of its own is told about a payment, and the invoice is paid once', function () {
    $seen = gcrGateway();
    $invoice = Invoice::factory()->create(['client_id' => Client::factory()->create()->id, 'status' => 'Unpaid', 'total' => 120, 'subtotal' => 120]);

    $this->withHeaders(['X-Fakepay-Signature' => 'good'])->post('/gateway/fakepay/callback', ['merchant_oid' => $invoice->id, 'total' => '120.00'])
        ->assertOk()->assertSeeText('OK');
    $this->withHeaders(['X-Fakepay-Signature' => 'good'])->post('/gateway/fakepay/callback', ['merchant_oid' => $invoice->id, 'total' => '120.00'])->assertOk();

    expect(strtolower($invoice->fresh()->status))->toBe('paid')
        ->and(\App\Models\Transaction::where('invoice_id', $invoice->id)->count())->toBe(1)
        ->and($seen[0]['_headers'])->toHaveKey('x-fakepay-signature')->and($seen[0])->toHaveKey('_raw_payload');
});

test('the module answers a bad call itself, and nothing is paid', function () {
    gcrGateway();
    $invoice = Invoice::factory()->create(['client_id' => Client::factory()->create()->id, 'status' => 'Unpaid', 'total' => 50, 'subtotal' => 50]);

    $this->withHeaders(['X-Fakepay-Signature' => 'forged'])->post('/gateway/fakepay/callback', ['merchant_oid' => $invoice->id, 'total' => '50'])
        ->assertStatus(400)->assertSeeText('BAD');

    expect(strtolower($invoice->fresh()->status))->toBe('unpaid');
});

test('a gateway switched off, unknown, or with a route of its own is not reached here', function () {
    $seen = gcrGateway('offpay', active: false);

    $this->post('/gateway/offpay/callback', ['x' => 1])->assertNotFound();
    $this->post('/gateway/nosuchpay/callback', ['x' => 1])->assertNotFound();
    $this->post('/gateway/stripe/callback', ['x' => 1])->assertNotFound();
    expect($seen)->toHaveCount(0);
});

test('iyzico keeps its own callback', function () {
    $route = app('router')->getRoutes()->match(Request::create('/gateway/iyzico/callback', 'POST'));

    expect($route->getName())->toBe('gateway.iyzico.callback');
});

test('a success without a transaction id is not applied, however often the provider retries', function () {
    $fake = Mockery::mock(GatewayModuleInterface::class);
    $fake->shouldReceive('getConfigFields')->andReturn([]);
    $fake->shouldReceive('getModuleName')->andReturn('noidpay');
    $fake->shouldReceive('processWebhook')->andReturnUsing(fn (array $data) => ['success' => true, 'invoice_id' => (int) $data['oid'], 'amount' => 100, 'response' => 'OK']);
    app()->instance(GatewayModuleInterface::class, $fake);
    app(ModuleRegistry::class)->registerGateway('noidpay', GatewayModuleInterface::class);
    GatewaySettings::updateOrCreate(['gateway' => 'noidpay', 'setting' => 'active'], ['value' => '1']);
    $client = Client::factory()->create(['credit' => 0]);
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'Paid', 'total' => 100, 'subtotal' => 100]);

    foreach (range(1, 3) as $retry) {
        $this->post('/gateway/noidpay/callback', ['oid' => $invoice->id])->assertOk()->assertSeeText('OK');
    }

    expect((float) $client->fresh()->credit)->toBe(0.0)
        ->and(\App\Models\Transaction::where('invoice_id', $invoice->id)->count())->toBe(0);
});
