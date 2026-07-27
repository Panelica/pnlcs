<?php

use App\Models\Admin;
use App\Models\Client;
use App\Models\Domain;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Crawls single-parameter GET detail pages with real fixtures. Detail views are
 * where wrong attribute reads hide: the page still returns 200 for a list, but
 * a show page dereferencing a missing relation blows up with a 500.
 */
function detailFixtures(): array
{
    $client = Client::factory()->create();
    $user = User::factory()->create();
    $user->clients()->attach($client->id);

    $server = Server::factory()->create(['type' => 'panelica']);
    $product = Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id, 'server_type' => 'panelica']);
    $order = Order::factory()->create(['client_id' => $client->id]);
    $service = Service::factory()->create([
        'client_id' => $client->id, 'product_id' => $product->id,
        'server_id' => $server->id, 'order_id' => $order->id, 'status' => 'active',
    ]);
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => 10]);
    InvoiceItem::create([
        'invoice_id' => $invoice->id, 'client_id' => $client->id, 'type' => 'Hosting',
        'rel_id' => $service->id, 'description' => 'Hosting', 'amount' => 10,
        'taxed' => false, 'due_date' => now(),
    ]);
    $domain = Domain::create([
        'client_id' => $client->id, 'domain' => 'crawl-example.com', 'type' => 'Register',
        'registrar' => 'Manual', 'status' => 'active', 'registration_period' => 1,
        'first_payment_amount' => 10, 'recurring_amount' => 10,
    ]);
    $ticket = Ticket::factory()->create([
        'client_id' => $client->id,
        'department_id' => TicketDepartment::factory()->create()->id,
        'status' => 'Open',
    ]);

    return compact('client', 'user', 'service', 'invoice', 'domain', 'ticket', 'order', 'product', 'server');
}

function crawlDetail(array $fx, string $prefix, $actor, ?string $guard = null): array
{
    // route parameter name => model instance
    $map = [
        'client' => $fx['client'], 'service' => $fx['service'], 'invoice' => $fx['invoice'],
        'domain' => $fx['domain'], 'ticket' => $fx['ticket'], 'order' => $fx['order'],
        'product' => $fx['product'], 'server' => $fx['server'],
    ];

    $broken = [];
    $visited = 0;
    foreach (Route::getRoutes() as $route) {
        if (! in_array('GET', $route->methods(), true)) {
            continue;
        }
        $name = $route->getName();
        if (! $name || ! str_starts_with($name, $prefix)) {
            continue;
        }
        $uri = $route->uri();
        if (! preg_match_all('/\{(\w+)\??\}/', $uri, $pm) || count($pm[1]) !== 1) {
            continue;
        }
        $param = $pm[1][0];
        if (! isset($map[$param])) {
            continue;
        }

        $url = '/'.ltrim(preg_replace('/\{'.$param.'\??\}/', (string) $map[$param]->getKey(), $uri), '/');
        try {
            $test = $guard ? test()->actingAs($actor, $guard) : test()->actingAs($actor);
            $status = $test->get($url)->getStatusCode();
        } catch (Throwable $e) {
            $broken[] = $name.' => EXCEPTION: '.substr($e->getMessage(), 0, 140);

            continue;
        }
        $visited++;
        if ($status >= 500) {
            $broken[] = $name.' ('.$url.') => HTTP '.$status;
        }
    }

    return ['broken' => $broken, 'visited' => $visited];
}

test('every admin detail page renders without a server error', function () {
    $fx = detailFixtures();

    $r = crawlDetail($fx, 'admin.', Admin::factory()->create(), 'admin');
    expect($r['visited'])->toBeGreaterThan(5);
    expect($r['broken'])->toBe([]);
});

test('every client detail page renders without a server error', function () {
    $fx = detailFixtures();

    $r = crawlDetail($fx, 'client.', $fx['user']);
    expect($r['visited'])->toBeGreaterThan(5);
    expect($r['broken'])->toBe([]);
});
