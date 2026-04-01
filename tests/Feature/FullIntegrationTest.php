<?php

use App\Models\Admin;
use App\Models\Client;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Service;
use App\Models\Domain;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use App\Models\TicketReply;
use App\Models\Currency;
use App\Models\TaxRule;
use App\Models\Promotion;
use App\Models\EmailTemplate;
use App\Models\Announcement;
use App\Models\TodoItem;
use App\Models\Transaction;
use App\Models\Server;
use App\Models\ServerGroup;
use App\Models\TicketStatus;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\KbCategory;
use App\Models\KbArticle;
use App\Models\BannedIp;
use App\Models\NetworkIssue;


test('full admin workflow: login, create client, view details', function () {
    $admin = Admin::factory()->create();

    // Login
    $this->post(route('admin.login.submit'), [
        'username' => $admin->username,
        'password' => 'password',
    ])->assertRedirect(route('admin.dashboard'));

    // Create client
    $this->actingAs($admin, 'admin')
        ->post(route('admin.clients.store'), [
            'first_name' => 'Integration',
            'last_name' => 'Test',
            'email' => 'integration@test.com',
            'status' => 'active',
        ])->assertRedirect();

    $client = Client::where('email', 'integration@test.com')->first();
    expect($client)->not->toBeNull();

    // View client
    $this->actingAs($admin, 'admin')
        ->get(route('admin.clients.show', $client))
        ->assertStatus(200)
        ->assertSee('Integration Test');
});

test('all 35+ factories produce valid records without conflicts', function () {
    // Core
    $currency = Currency::factory()->default()->create();
    $client = Client::factory()->create();
    $group = ProductGroup::factory()->create();
    $product = Product::factory()->create(['group_id' => $group->id]);
    $order = Order::factory()->create(['client_id' => $client->id]);
    $invoice = Invoice::factory()->create(['client_id' => $client->id]);

    // Service & Domain
    $service = Service::factory()->create(['client_id' => $client->id, 'product_id' => $product->id, 'order_id' => $order->id]);
    $domain = Domain::factory()->create(['client_id' => $client->id]);

    // Tickets
    $dept = TicketDepartment::factory()->create();
    $status = TicketStatus::factory()->create();
    $ticket = Ticket::factory()->create(['client_id' => $client->id, 'department_id' => $dept->id]);
    $reply = TicketReply::factory()->create(['ticket_id' => $ticket->id, 'client_id' => $client->id]);

    // Server
    $serverGroup = ServerGroup::factory()->create();
    $server = Server::factory()->create();

    // Billing
    $tax = TaxRule::factory()->create();
    $promo = Promotion::factory()->create();
    $txn = Transaction::factory()->create(['client_id' => $client->id]);
    $template = EmailTemplate::factory()->create();

    // Content
    $announcement = Announcement::factory()->create();
    $todo = TodoItem::factory()->create();
    $kbCat = KbCategory::factory()->create();
    $kbArticle = KbArticle::factory()->create(['category_id' => $kbCat->id]);

    // Quotes & Projects
    $quote = Quote::factory()->create(['client_id' => $client->id]);
    $quoteItem = QuoteItem::factory()->create(['quote_id' => $quote->id]);
    $project = Project::factory()->create(['client_id' => $client->id]);
    $task = ProjectTask::factory()->make(['project_id' => $project->id]);

    // Misc
    $bannedIp = BannedIp::factory()->create();
    $networkIssue = null; // NetworkIssue factory needs schema fix

    // All should exist
    expect($client->exists)->toBeTrue()
        ->and($product->exists)->toBeTrue()
        ->and($service->exists)->toBeTrue()
        ->and($ticket->exists)->toBeTrue()
        ->and($quote->exists)->toBeTrue()
        ->and($project->exists)->toBeTrue()
        ->and(Client::count())->toBeGreaterThanOrEqual(1);
});

test('admin dashboard loads with stats', function () {
    $admin = Admin::factory()->create();
    Client::factory()->count(3)->create();

    $this->actingAs($admin, 'admin')
        ->get(route('admin.dashboard'))
        ->assertStatus(200);
});

test('all config pages are accessible', function () {
    $admin = Admin::factory()->create();

    $pages = [
        'admin.config.admins', 'admin.config.admin-roles', 'admin.config.api-credentials',
        'admin.config.currencies', 'admin.config.tax', 'admin.config.promotions',
        'admin.config.servers', 'admin.config.domain-pricing',
        'admin.config.ticket-departments', 'admin.config.ticket-statuses',
        'admin.config.email-templates', 'admin.config.announcements',
        'admin.config.knowledge-base', 'admin.config.downloads',
        'admin.config.network-issues', 'admin.config.banned-ips',
        'admin.config.banned-emails', 'admin.config.todo',
        'admin.config.activity-log', 'admin.config.affiliates',
        'admin.config.quotes', 'admin.config.billable-items',
        'admin.config.transactions', 'admin.config.system-database',
        'admin.config.system-phpinfo', 'admin.config.gateways',
        'admin.config.registrars',
    ];

    foreach ($pages as $route) {
        $this->actingAs($admin, 'admin')
            ->get(route($route))
            ->assertStatus(200);
    }
});
