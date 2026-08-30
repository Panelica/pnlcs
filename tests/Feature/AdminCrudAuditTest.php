<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Announcement;
use App\Models\BannedEmail;
use App\Models\BannedIp;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Domain;
use App\Models\DomainPricing;
use App\Models\EmailTemplate;
use App\Models\Invoice;
use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\NetworkIssue;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\Promotion;
use App\Models\Quote;
use App\Models\Server;
use App\Models\ServerGroup;
use App\Models\TaxRule;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use App\Models\TicketStatus;
use App\Models\TodoItem;

beforeEach(function () {
    $this->admin = Admin::factory()->create();
});

// === CLIENT CRUD ===
test('POST /admin/clients creates client', function () {
    $this->actingAs($this->admin, 'admin')
        ->post('/admin/clients', ['first_name' => 'Test', 'last_name' => 'User', 'email' => 'crud@test.com', 'status' => 'active'])
        ->assertRedirect();
    $this->assertDatabaseHas('clients', ['email' => 'crud@test.com']);
});

test('PUT /admin/clients/{id} updates client', function () {
    $client = Client::factory()->create();
    $this->actingAs($this->admin, 'admin')
        ->put("/admin/clients/{$client->id}", ['first_name' => 'Updated', 'last_name' => 'Name', 'email' => $client->email, 'status' => 'active'])
        ->assertRedirect();
});

test('DELETE /admin/clients/{id} deletes client', function () {
    $client = Client::factory()->create();
    $this->actingAs($this->admin, 'admin')->delete("/admin/clients/{$client->id}")->assertRedirect();
});

// === CURRENCY CRUD ===
test('POST currencies creates', function () {
    $this->actingAs($this->admin, 'admin')
        ->post('/admin/config/currencies', ['code' => 'XYZ', 'prefix' => 'X', 'suffix' => '', 'rate' => 1.5])
        ->assertRedirect();
    $this->assertDatabaseHas('currencies', ['code' => 'XYZ']);
});

test('PUT currencies updates', function () {
    $c = Currency::factory()->create();
    $this->actingAs($this->admin, 'admin')
        ->put("/admin/config/currencies/{$c->id}", ['code' => $c->code, 'prefix' => '$$', 'suffix' => '', 'rate' => 2])
        ->assertRedirect();
});

test('DELETE currencies deletes', function () {
    $c = Currency::factory()->create();
    $this->actingAs($this->admin, 'admin')->delete("/admin/config/currencies/{$c->id}")->assertRedirect();
});

// === TAX CRUD ===
test('POST tax creates', function () {
    $this->actingAs($this->admin, 'admin')
        ->post('/admin/config/tax', ['country' => 'US', 'rates' => [['name' => 'TestTax', 'tax_rate' => 15]]])
        ->assertRedirect();
    $this->assertDatabaseHas('tax_rules', ['name' => 'TestTax']);
});

test('PUT tax updates', function () {
    $t = TaxRule::factory()->create(['country' => 'US']);
    $this->actingAs($this->admin, 'admin')
        ->put("/admin/config/tax/{$t->country}", ['country' => 'DE', 'rates' => [['name' => 'Updated', 'tax_rate' => 20]]])
        ->assertRedirect();
});

test('DELETE tax deletes', function () {
    $t = TaxRule::factory()->create();
    $this->actingAs($this->admin, 'admin')->delete("/admin/config/tax/{$t->country}")->assertRedirect();
});

// === PROMOTIONS CRUD ===
test('POST promotions creates', function () {
    $this->actingAs($this->admin, 'admin')
        ->post('/admin/config/promotions', ['code' => 'TESTPROMO', 'type' => 'percentage', 'value' => 10])
        ->assertRedirect();
    $this->assertDatabaseHas('promotions', ['code' => 'TESTPROMO']);
});

test('DELETE promotions deletes', function () {
    $p = Promotion::factory()->create();
    $this->actingAs($this->admin, 'admin')->delete("/admin/config/promotions/{$p->id}")->assertRedirect();
});

// === SERVERS CRUD ===
test('POST servers creates', function () {
    $this->actingAs($this->admin, 'admin')
        ->post('/admin/config/servers', ['name' => 'TestSrv', 'hostname' => 'test.srv.com', 'type' => 'custom'])
        ->assertRedirect();
    $this->assertDatabaseHas('servers', ['name' => 'TestSrv']);
});

test('DELETE servers deletes', function () {
    $s = Server::factory()->create();
    $this->actingAs($this->admin, 'admin')->delete("/admin/config/servers/{$s->id}")->assertRedirect();
});

// === ADMIN ROLES CRUD ===
test('POST admin-roles creates', function () {
    $this->actingAs($this->admin, 'admin')
        ->post('/admin/config/admin-roles', ['name' => 'TestRole', 'description' => 'Test'])
        ->assertRedirect();
    $this->assertDatabaseHas('admin_roles', ['name' => 'TestRole']);
});

test('DELETE admin-roles deletes', function () {
    $r = AdminRole::factory()->create();
    $this->actingAs($this->admin, 'admin')->delete("/admin/config/admin-roles/{$r->id}")->assertRedirect();
});

// === TICKET DEPARTMENTS CRUD ===
test('POST ticket-departments creates', function () {
    $this->actingAs($this->admin, 'admin')
        ->post('/admin/config/ticket-departments', ['name' => 'TestDept', 'email' => 'dept@test.com'])
        ->assertRedirect();
    $this->assertDatabaseHas('ticket_departments', ['name' => 'TestDept']);
});

test('DELETE ticket-departments deletes', function () {
    $d = TicketDepartment::factory()->create();
    $this->actingAs($this->admin, 'admin')->delete("/admin/config/ticket-departments/{$d->id}")->assertRedirect();
});

// === TICKET STATUSES CRUD ===
test('POST ticket-statuses creates', function () {
    $this->actingAs($this->admin, 'admin')
        ->post('/admin/config/ticket-statuses', ['title' => 'TestStatus', 'color' => '#ff0000'])
        ->assertRedirect();
    $this->assertDatabaseHas('ticket_statuses', ['title' => 'TestStatus']);
});

test('DELETE ticket-statuses deletes', function () {
    $s = TicketStatus::factory()->create();
    $this->actingAs($this->admin, 'admin')->delete("/admin/config/ticket-statuses/{$s->id}")->assertRedirect();
});

// === ANNOUNCEMENTS CRUD ===
test('POST announcements creates', function () {
    $this->actingAs($this->admin, 'admin')
        ->post('/admin/config/announcements', ['title' => 'TestAnn', 'announcement' => 'Test body'])
        ->assertRedirect();
    $this->assertDatabaseHas('announcements', ['title' => 'TestAnn']);
});

test('DELETE announcements deletes', function () {
    $a = Announcement::factory()->create();
    $this->actingAs($this->admin, 'admin')->delete("/admin/config/announcements/{$a->id}")->assertRedirect();
});

// === TODO CRUD ===
test('POST todo creates', function () {
    $this->actingAs($this->admin, 'admin')
        ->post('/admin/config/todo', ['title' => 'TestTodo'])
        ->assertRedirect();
    $this->assertDatabaseHas('todo_items', ['title' => 'TestTodo']);
});

test('PUT todo updates', function () {
    $t = TodoItem::factory()->create();
    $this->actingAs($this->admin, 'admin')
        ->put("/admin/config/todo/{$t->id}", ['title' => 'Updated', 'status' => 'Completed'])
        ->assertRedirect();
});

test('DELETE todo deletes', function () {
    $t = TodoItem::factory()->create();
    $this->actingAs($this->admin, 'admin')->delete("/admin/config/todo/{$t->id}")->assertRedirect();
});

// === BANNED IPS ===
test('POST banned-ips creates', function () {
    $this->actingAs($this->admin, 'admin')
        ->post('/admin/config/banned-ips', ['ip' => '1.2.3.4', 'reason' => 'test'])
        ->assertRedirect();
    $this->assertDatabaseHas('banned_ips', ['ip' => '1.2.3.4']);
});

test('DELETE banned-ips deletes', function () {
    $b = BannedIp::factory()->create();
    $this->actingAs($this->admin, 'admin')->delete("/admin/config/banned-ips/{$b->id}")->assertRedirect();
});

// === BANNED EMAILS ===
test('POST banned-emails creates', function () {
    $this->actingAs($this->admin, 'admin')
        ->post('/admin/config/banned-emails', ['domain' => 'spam@bad.com', 'reason' => 'spam'])
        ->assertRedirect();
    $this->assertDatabaseHas('banned_emails', ['domain' => 'spam@bad.com']);
});

// === NETWORK ISSUES ===
test('POST network-issues creates', function () {
    $this->actingAs($this->admin, 'admin')
        ->post('/admin/config/network-issues', ['title' => 'TestIssue', 'description' => 'Test description', 'status' => 'Reported'])
        ->assertRedirect();
    $this->assertDatabaseHas('network_issues', ['title' => 'TestIssue']);
});

// === ORDERS ACTIONS ===
test('POST orders/{id}/accept works', function () {
    $order = Order::factory()->pending()->create();
    $this->actingAs($this->admin, 'admin')->post("/admin/orders/{$order->id}/accept")->assertRedirect();
});

test('POST orders/{id}/cancel works', function () {
    $order = Order::factory()->create();
    $this->actingAs($this->admin, 'admin')->post("/admin/orders/{$order->id}/cancel")->assertRedirect();
});

// === INVOICES ACTIONS ===
test('POST invoices/{id}/mark-paid works', function () {
    $invoice = Invoice::factory()->create();
    $this->actingAs($this->admin, 'admin')
        ->post("/admin/invoices/{$invoice->id}/mark-paid", ['gateway' => 'manual'])
        ->assertRedirect();
});

test('POST invoices/{id}/cancel works', function () {
    $invoice = Invoice::factory()->create();
    $this->actingAs($this->admin, 'admin')->post("/admin/invoices/{$invoice->id}/cancel")->assertRedirect();
});

// === TICKETS ===
test('POST tickets/{id}/reply works', function () {
    $ticket = Ticket::factory()->create();
    $this->actingAs($this->admin, 'admin')
        ->post("/admin/tickets/{$ticket->id}/reply", ['message' => 'Test reply from admin'])
        ->assertRedirect();
});

// === PRODUCTS ===
test('POST products creates', function () {
    $g = ProductGroup::factory()->create();
    $this->actingAs($this->admin, 'admin')
        ->post('/admin/products', ['name' => 'TestProd', 'group_id' => $g->id, 'type' => 'hostingaccount', 'pay_type' => 'recurring'])
        ->assertRedirect();
});

// === PROJECTS ===
test('POST projects creates', function () {
    $client = Client::factory()->create();
    $this->actingAs($this->admin, 'admin')
        ->post('/admin/projects', ['title' => 'TestProject', 'client_id' => $client->id, 'status' => 'Active'])
        ->assertRedirect();
});

// === QUOTES ===
test('POST quotes creates', function () {
    $client = Client::factory()->create();
    $this->actingAs($this->admin, 'admin')
        ->post('/admin/quotes', ['client_id' => $client->id, 'subject' => 'TestQuote', 'valid_until' => '2026-12-31'])
        ->assertRedirect();
});

// === SETTINGS ===
test('POST settings updates', function () {
    $this->actingAs($this->admin, 'admin')
        ->post('/admin/settings', ['CompanyName' => 'TestCo', 'Domain' => 'test.com'])
        ->assertRedirect();
});

// === LOGIN ===
test('POST login with valid credentials redirects to dashboard', function () {
    $admin = Admin::factory()->create(['username' => 'logintest', 'password' => 'secret123']);
    $this->post('/admin/login', ['username' => 'logintest', 'password' => 'secret123'])->assertRedirect('/admin');
});

test('POST login with wrong password shows error', function () {
    $admin = Admin::factory()->create(['username' => 'wrongtest']);
    $this->post('/admin/login', ['username' => 'wrongtest', 'password' => 'wrong'])->assertRedirect();
});
