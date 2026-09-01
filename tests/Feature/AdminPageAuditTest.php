<?php

use App\Models\Admin;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Service;
use App\Models\Domain;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Project;
use App\Models\Quote;

beforeEach(function () {
    $this->admin = Admin::factory()->create();
});

// Dashboard
test('GET /admin → 200', function () {
    $this->actingAs($this->admin, 'admin')->get('/admin')->assertStatus(200);
});

// Clients
test('GET /admin/clients → 200', function () {
    $this->actingAs($this->admin, 'admin')->get('/admin/clients')->assertStatus(200);
});
test('GET /admin/clients/create → 200', function () {
    $this->actingAs($this->admin, 'admin')->get('/admin/clients/create')->assertStatus(200);
});
test('GET /admin/clients/{id} → 200', function () {
    $client = Client::factory()->create();
    $this->actingAs($this->admin, 'admin')->get("/admin/clients/{$client->id}")->assertStatus(200);
});
test('GET /admin/clients/{id}/edit → 200', function () {
    $client = Client::factory()->create();
    $this->actingAs($this->admin, 'admin')->get("/admin/clients/{$client->id}/edit")->assertStatus(200);
});

// Orders
test('GET /admin/orders → 200', function () {
    $this->actingAs($this->admin, 'admin')->get('/admin/orders')->assertStatus(200);
});
test('GET /admin/orders/{id} → 200', function () {
    $order = Order::factory()->create();
    $this->actingAs($this->admin, 'admin')->get("/admin/orders/{$order->id}")->assertStatus(200);
});

// Invoices
test('GET /admin/invoices → 200', function () {
    $this->actingAs($this->admin, 'admin')->get('/admin/invoices')->assertStatus(200);
});
test('GET /admin/invoices/create → 200', function () {
    $this->actingAs($this->admin, 'admin')->get('/admin/invoices/create')->assertStatus(200);
});
test('GET /admin/invoices/{id} → 200', function () {
    $invoice = Invoice::factory()->create();
    $this->actingAs($this->admin, 'admin')->get("/admin/invoices/{$invoice->id}")->assertStatus(200);
});

// Services
test('GET /admin/services → 200', function () {
    $this->actingAs($this->admin, 'admin')->get('/admin/services')->assertStatus(200);
});
test('GET /admin/services/{id} → 200', function () {
    $service = Service::factory()->create();
    $this->actingAs($this->admin, 'admin')->get("/admin/services/{$service->id}")->assertStatus(200);
});

// Domains
test('GET /admin/domains → 200', function () {
    $this->actingAs($this->admin, 'admin')->get('/admin/domains')->assertStatus(200);
});
test('GET /admin/domains/{id} → 200', function () {
    $domain = Domain::factory()->create();
    $this->actingAs($this->admin, 'admin')->get("/admin/domains/{$domain->id}")->assertStatus(200);
});

// Tickets
test('GET /admin/tickets → 200', function () {
    $this->actingAs($this->admin, 'admin')->get('/admin/tickets')->assertStatus(200);
});
test('GET /admin/tickets/{id} → 200', function () {
    $ticket = Ticket::factory()->create();
    $this->actingAs($this->admin, 'admin')->get("/admin/tickets/{$ticket->id}")->assertStatus(200);
});

// Products
test('GET /admin/products → 200', function () {
    $this->actingAs($this->admin, 'admin')->get('/admin/products')->assertStatus(200);
});
test('GET /admin/products/create → 200', function () {
    $this->actingAs($this->admin, 'admin')->get('/admin/products/create')->assertStatus(200);
});
test('GET /admin/products/{id}/edit → 200', function () {
    $product = Product::factory()->create();
    $this->actingAs($this->admin, 'admin')->get("/admin/products/{$product->id}/edit")->assertStatus(200);
});

// Projects
test('GET /admin/projects → 200', function () {
    $this->actingAs($this->admin, 'admin')->get('/admin/projects')->assertStatus(200);
});
test('GET /admin/projects/create → 200', function () {
    $this->actingAs($this->admin, 'admin')->get('/admin/projects/create')->assertStatus(200);
});
test('GET /admin/projects/{id} → 200', function () {
    $project = Project::factory()->create();
    $this->actingAs($this->admin, 'admin')->get("/admin/projects/{$project->id}")->assertStatus(200);
});

// Quotes
test('GET /admin/quotes → 200', function () {
    $this->actingAs($this->admin, 'admin')->get('/admin/quotes')->assertStatus(200);
});
test('GET /admin/quotes/create → 200', function () {
    $this->actingAs($this->admin, 'admin')->get('/admin/quotes/create')->assertStatus(200);
});
test('GET /admin/quotes/{id} → 200', function () {
    $quote = Quote::factory()->create();
    $this->actingAs($this->admin, 'admin')->get("/admin/quotes/{$quote->id}")->assertStatus(200);
});

// Reports
test('GET /admin/reports → 200', function () {
    $this->actingAs($this->admin, 'admin')->get('/admin/reports')->assertStatus(200);
});
test('GET /admin/reports/income-summary → 200', function () {
    $this->actingAs($this->admin, 'admin')->get('/admin/reports/income-summary')->assertStatus(200);
});

// Settings + Logs
test('GET /admin/settings → 200', function () {
    $this->actingAs($this->admin, 'admin')->get('/admin/settings')->assertStatus(200);
});
test('GET /admin/logs → 200', function () {
    $this->actingAs($this->admin, 'admin')->get('/admin/logs')->assertStatus(200);
});
test('GET /admin/logs/gateway → 200', function () {
    $this->actingAs($this->admin, 'admin')->get('/admin/logs/gateway')->assertStatus(200);
});
test('GET /admin/logs/module → 200', function () {
    $this->actingAs($this->admin, 'admin')->get('/admin/logs/module')->assertStatus(200);
});
test('GET /admin/logs/email → 200', function () {
    $this->actingAs($this->admin, 'admin')->get('/admin/logs/email')->assertStatus(200);
});

// ALL 35 Config pages
$configPages = [
    'admins', 'admin-roles', 'api-credentials', 'currencies', 'tax', 'promotions',
    'servers', 'server-groups', 'domain-pricing', 'gateways', 'registrars',
    'ticket-departments', 'ticket-statuses', 'email-templates', 'announcements',
    'knowledge-base', 'downloads', 'network-issues', 'banned-ips', 'banned-emails',
    'todo', 'activity-log', 'quotes', 'billable-items', 'transactions',
    'system-database', 'system-phpinfo', 'automation', 'client-groups',
];

foreach ($configPages as $page) {
    test("GET /admin/config/{$page} → 200", function () use ($page) {
        $this->actingAs($this->admin, 'admin')->get("/admin/config/{$page}")->assertStatus(200);
    });
}

// Auth
test('GET /admin/login → 200 (unauthenticated)', function () {
    $this->get('/admin/login')->assertStatus(200);
});
test('unauthenticated redirects to login', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});
