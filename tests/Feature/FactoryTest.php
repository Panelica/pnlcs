<?php

use App\Models\Announcement;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Domain;
use App\Models\EmailTemplate;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Promotion;
use App\Models\Server;
use App\Models\ServerGroup;
use App\Models\Service;
use App\Models\TaxRule;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use App\Models\TicketReply;
use App\Models\TicketStatus;
use App\Models\TodoItem;
use App\Models\Transaction;


test('currency factory creates valid record', function () {
    $currency = Currency::factory()->create();
    expect($currency)->toBeInstanceOf(Currency::class)
        ->and($currency->code)->not->toBeNull()
        ->and($currency->rate)->toBeGreaterThan(0);
});

test('currency factory default state works', function () {
    $currency = Currency::factory()->default()->create();
    expect($currency->code)->toBe('USD')
        ->and($currency->is_default)->toBeTrue();
});

test('invoice factory creates valid record', function () {
    $invoice = Invoice::factory()->create();
    expect($invoice)->toBeInstanceOf(Invoice::class)
        ->and($invoice->client_id)->not->toBeNull()
        ->and($invoice->status)->toBe('Unpaid');
});

test('invoice factory paid state works', function () {
    $invoice = Invoice::factory()->paid()->create();
    expect($invoice->status)->toBe('Paid')
        ->and($invoice->date_paid)->not->toBeNull();
});

test('invoice item factory creates valid record', function () {
    $item = InvoiceItem::factory()->create();
    expect($item)->toBeInstanceOf(InvoiceItem::class)
        ->and($item->invoice_id)->not->toBeNull()
        ->and($item->amount)->toBeGreaterThan(0);
});

test('service factory creates valid record', function () {
    $service = Service::factory()->create();
    expect($service)->toBeInstanceOf(Service::class)
        ->and($service->client_id)->not->toBeNull()
        ->and($service->status)->toBe('Active');
});

test('service factory suspended state works', function () {
    $service = Service::factory()->suspended()->create();
    expect($service->status)->toBe('Suspended')
        ->and($service->suspension_date)->not->toBeNull();
});

test('product factory creates valid record', function () {
    $product = Product::factory()->create();
    expect($product)->toBeInstanceOf(Product::class)
        ->and($product->name)->not->toBeNull()
        ->and($product->group_id)->not->toBeNull();
});

test('product group factory creates valid record', function () {
    $group = ProductGroup::factory()->create();
    expect($group)->toBeInstanceOf(ProductGroup::class)
        ->and($group->name)->not->toBeNull()
        ->and($group->slug)->not->toBeNull();
});

test('order factory creates valid record', function () {
    $order = Order::factory()->create();
    expect($order)->toBeInstanceOf(Order::class)
        ->and($order->client_id)->not->toBeNull()
        ->and($order->status)->toBe('Active');
});

test('domain factory creates valid record', function () {
    $domain = Domain::factory()->create();
    expect($domain)->toBeInstanceOf(Domain::class)
        ->and($domain->domain)->not->toBeNull()
        ->and($domain->status)->toBe('Active');
});

test('domain factory transfer state works', function () {
    $domain = Domain::factory()->transfer()->create();
    expect($domain->type)->toBe('Transfer');
});

test('ticket factory creates valid record', function () {
    $ticket = Ticket::factory()->create();
    expect($ticket)->toBeInstanceOf(Ticket::class)
        ->and($ticket->title)->not->toBeNull()
        ->and($ticket->department_id)->not->toBeNull();
});

test('ticket department factory creates valid record', function () {
    $dept = TicketDepartment::factory()->create();
    expect($dept)->toBeInstanceOf(TicketDepartment::class)
        ->and($dept->name)->not->toBeNull();
});

test('ticket status factory creates valid record', function () {
    $status = TicketStatus::factory()->create();
    expect($status)->toBeInstanceOf(TicketStatus::class)
        ->and($status->title)->not->toBeNull();
});

test('ticket reply factory creates valid record', function () {
    $reply = TicketReply::factory()->create();
    expect($reply)->toBeInstanceOf(TicketReply::class)
        ->and($reply->ticket_id)->not->toBeNull()
        ->and($reply->message)->not->toBeNull();
});

test('ticket reply factory admin state works', function () {
    $reply = TicketReply::factory()->fromAdmin('TestAdmin')->create();
    expect($reply->admin)->toBe('TestAdmin')
        ->and($reply->client_id)->toBeNull();
});

test('server factory creates valid record', function () {
    $server = Server::factory()->create();
    expect($server)->toBeInstanceOf(Server::class)
        ->and($server->hostname)->not->toBeNull()
        ->and($server->ip_address)->not->toBeNull();
});

test('server group factory creates valid record', function () {
    $group = ServerGroup::factory()->create();
    expect($group)->toBeInstanceOf(ServerGroup::class)
        ->and($group->name)->not->toBeNull();
});

test('promotion factory creates valid record', function () {
    $promo = Promotion::factory()->create();
    expect($promo)->toBeInstanceOf(Promotion::class)
        ->and($promo->code)->not->toBeNull()
        ->and($promo->value)->toBeGreaterThan(0);
});

test('promotion factory expired state works', function () {
    $promo = Promotion::factory()->expired()->create();
    expect($promo->expiration_date->isPast())->toBeTrue();
});

test('tax rule factory creates valid record', function () {
    $tax = TaxRule::factory()->create();
    expect($tax)->toBeInstanceOf(TaxRule::class)
        ->and($tax->name)->not->toBeNull()
        ->and($tax->tax_rate)->toBeGreaterThan(0);
});

test('email template factory creates valid record', function () {
    $template = EmailTemplate::factory()->create();
    expect($template)->toBeInstanceOf(EmailTemplate::class)
        ->and($template->name)->not->toBeNull()
        ->and($template->subject)->not->toBeNull();
});

test('announcement factory creates valid record', function () {
    $ann = Announcement::factory()->create();
    expect($ann)->toBeInstanceOf(Announcement::class)
        ->and($ann->title)->not->toBeNull()
        ->and($ann->published)->toBeTrue();
});

test('announcement factory draft state works', function () {
    $ann = Announcement::factory()->draft()->create();
    expect($ann->published)->toBeFalse();
});

test('todo item factory creates valid record', function () {
    $todo = TodoItem::factory()->create();
    expect($todo)->toBeInstanceOf(TodoItem::class)
        ->and($todo->title)->not->toBeNull();
});

test('transaction factory creates valid record', function () {
    $txn = Transaction::factory()->create();
    expect($txn)->toBeInstanceOf(Transaction::class)
        ->and($txn->client_id)->not->toBeNull()
        ->and($txn->amount_in)->toBeGreaterThan(0);
});

test('transaction factory refund state works', function () {
    $txn = Transaction::factory()->refund()->create();
    expect((float) $txn->amount_in)->toBe(0.0)
        ->and($txn->amount_out)->toBeGreaterThan(0);
});

test('all 20 factories create valid records in batch', function () {
    // Create one of each to verify no conflicts
    $currency = Currency::factory()->default()->create();
    $client = Client::factory()->create();
    $group = ProductGroup::factory()->create();
    $product = Product::factory()->create(['group_id' => $group->id]);
    $order = Order::factory()->create(['client_id' => $client->id]);
    $invoice = Invoice::factory()->create(['client_id' => $client->id]);
    $item = InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'client_id' => $client->id]);
    $service = Service::factory()->create(['client_id' => $client->id, 'product_id' => $product->id, 'order_id' => $order->id]);
    $domain = Domain::factory()->create(['client_id' => $client->id]);
    $dept = TicketDepartment::factory()->create();
    $status = TicketStatus::factory()->create();
    $ticket = Ticket::factory()->create(['client_id' => $client->id, 'department_id' => $dept->id]);
    $reply = TicketReply::factory()->create(['ticket_id' => $ticket->id, 'client_id' => $client->id]);
    $serverGroup = ServerGroup::factory()->create();
    $server = Server::factory()->create();
    $promo = Promotion::factory()->create();
    $tax = TaxRule::factory()->create();
    $template = EmailTemplate::factory()->create();
    $announcement = Announcement::factory()->create();
    $todo = TodoItem::factory()->create();
    $txn = Transaction::factory()->create(['client_id' => $client->id]);

    expect($currency->exists)->toBeTrue()
        ->and($client->exists)->toBeTrue()
        ->and($product->exists)->toBeTrue()
        ->and($order->exists)->toBeTrue()
        ->and($invoice->exists)->toBeTrue()
        ->and($item->exists)->toBeTrue()
        ->and($service->exists)->toBeTrue()
        ->and($domain->exists)->toBeTrue()
        ->and($ticket->exists)->toBeTrue()
        ->and($reply->exists)->toBeTrue()
        ->and($server->exists)->toBeTrue()
        ->and($promo->exists)->toBeTrue()
        ->and($tax->exists)->toBeTrue()
        ->and($template->exists)->toBeTrue()
        ->and($announcement->exists)->toBeTrue()
        ->and($todo->exists)->toBeTrue()
        ->and($txn->exists)->toBeTrue();
});
