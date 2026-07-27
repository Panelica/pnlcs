<?php

use App\Models\Client;
use App\Models\Domain;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schedule;

/**
 * Every scheduled command is executed against realistic data. These run
 * unattended in production — a crash is invisible until someone notices the
 * work silently stopped happening (no invoices generated, no suspensions, no
 * reminders). The page crawl does this for the UI; this is the same net for
 * the cron surface.
 */
function cronFixtures(): void
{
    $client = Client::factory()->create();
    $server = Server::factory()->create(['type' => 'panelica', 'active' => true]);
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'server_type' => 'panelica',
        'overage_enabled' => true,
        'overage_disk_rate' => 0.05,
        'overage_bw_rate' => 0.01,
    ]);

    // A service due for renewal, over its limits, plus a suspended one.
    Service::factory()->create([
        'client_id' => $client->id, 'product_id' => $product->id, 'server_id' => $server->id,
        'status' => 'active', 'next_due_date' => now()->addDays(3), 'amount' => 20,
        'billing_cycle' => 'Monthly', 'disk_usage' => 5000, 'disk_limit' => 1000,
        'bw_usage' => 20000, 'bw_limit' => 10000, 'username' => 'cronuser',
        'module_data' => ['panelica_user_id' => 99],
    ]);
    Service::factory()->create([
        'client_id' => $client->id, 'product_id' => $product->id, 'server_id' => $server->id,
        'status' => 'suspended', 'next_due_date' => now()->subDays(20), 'amount' => 20,
    ]);

    // Overdue and unpaid invoices.
    $overdue = Invoice::factory()->create([
        'client_id' => $client->id, 'status' => 'overdue',
        'due_date' => now()->subDays(30), 'total' => 50, 'subtotal' => 50,
    ]);
    InvoiceItem::create([
        'invoice_id' => $overdue->id, 'client_id' => $client->id, 'type' => 'Hosting',
        'rel_id' => 1, 'description' => 'x', 'amount' => 50, 'taxed' => false, 'due_date' => now()->subDays(30),
    ]);
    Invoice::factory()->create([
        'client_id' => $client->id, 'status' => 'unpaid',
        'due_date' => now()->subDay(), 'total' => 10, 'subtotal' => 10,
    ]);

    // A domain due for renewal and a ticket.
    Domain::create([
        'client_id' => $client->id, 'domain' => 'cron-example.com', 'type' => 'Register',
        'registrar' => 'Manual', 'status' => 'active', 'registration_period' => 1,
        'expiry_date' => now()->addDays(10), 'next_due_date' => now()->addDays(5),
        'first_payment_amount' => 12, 'recurring_amount' => 12,
    ]);
    Ticket::factory()->create([
        'client_id' => $client->id,
        'department_id' => TicketDepartment::factory()->create()->id,
        'status' => 'Open', 'last_reply' => now()->subDays(2),
    ]);
}

test('every scheduled command runs without crashing', function () {
    Http::fake(['*' => Http::response(['success' => true, 'result' => []], 200)]);
    cronFixtures();

    $commands = [];
    foreach (Schedule::events() as $event) {
        if (preg_match('/artisan[\'"]? (pnlcs:[a-z\-]+)/', $event->command ?? '', $m)) {
            $commands[] = $m[1];
        }
    }
    // Anything that shells out to mysqldump or talks to a real mailbox is left
    // to its own dedicated test.
    $commands = array_values(array_diff(array_unique($commands), ['pnlcs:db-backup', 'pnlcs:mail-import']));

    expect(count($commands))->toBeGreaterThan(12);

    $broken = [];
    foreach ($commands as $command) {
        try {
            $exit = $this->artisan($command)->run();
            if ($exit !== 0) {
                $broken[] = "$command => exit $exit";
            }
        } catch (Throwable $e) {
            $broken[] = "$command => ".get_class($e).': '.substr($e->getMessage(), 0, 160);
        }
    }

    expect($broken)->toBe([]);
});

test('the renewal cron produces invoices for services that are due', function () {
    Http::fake(['*' => Http::response(['success' => true], 200)]);
    cronFixtures();

    $before = Invoice::count();
    $this->artisan('pnlcs:generate-invoices')->assertSuccessful();

    expect(Invoice::count())->toBeGreaterThan($before);
});
