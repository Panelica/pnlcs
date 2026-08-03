<?php

use App\Models\Client;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\Service;
use Illuminate\Http\Request;
use Modules\Reports\ServerRevenueReport;

/**
 * Revenue per server.
 *
 * The report summed services.amount and printed it under "Monthly Revenue"
 * without looking at how often that amount is actually charged. On the live
 * install every active service is billed annually, so the forecast was twelve
 * times what the servers really bring in each month.
 */
function forecastService(string $cycle, float $amount, Server $server): Service
{
    $product = Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id]);

    return Service::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'product_id' => $product->id,
        'server_id' => $server->id,
        'status' => 'active',
        'billing_cycle' => $cycle,
        'amount' => $amount,
    ]);
}

function forecastRows(): array
{
    return (new ServerRevenueReport)->generate(Request::create('/'))['rows'];
}

it('spreads a yearly price across the year', function () {
    $server = Server::factory()->create(['name' => 'Forecast One', 'type' => 'cpanel']);
    forecastService('Annually', 120.00, $server);

    $row = collect(forecastRows())->firstWhere('server', 'Forecast One');

    expect(round((float) $row->monthly_revenue, 2))->toBe(10.00);
});

it('handles every billing cycle the cart can sell', function () {
    $server = Server::factory()->create(['name' => 'Forecast Two', 'type' => 'cpanel']);

    forecastService('Monthly', 10.00, $server);        // 10
    forecastService('Quarterly', 30.00, $server);      // 10
    forecastService('Semi-Annually', 60.00, $server);  // 10
    forecastService('Annually', 120.00, $server);      // 10
    forecastService('Biennially', 240.00, $server);    // 10
    forecastService('Triennially', 360.00, $server);   // 10

    $row = collect(forecastRows())->firstWhere('server', 'Forecast Two');

    expect(round((float) $row->monthly_revenue, 2))->toBe(60.00)
        ->and((int) $row->services_count)->toBe(6);
});
