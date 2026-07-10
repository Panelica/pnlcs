<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use App\Models\TicketReply;
use App\Models\Domain;
use App\Services\ClientService;
use App\Services\TicketService;
use App\Services\DomainService;
use App\Services\TransactionService;
use App\Services\AffiliateService;


// ClientService tests
test('client service creates client', function () {
    $service = new ClientService();
    $client = $service->createClient([
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@test.com',
        'status' => 'active',
    ]);
    expect($client)->toBeInstanceOf(Client::class)
        ->and($client->email)->toBe('john@test.com');
});

test('client service adds credit', function () {
    $service = new ClientService();
    $client = Client::factory()->create(['credit' => 10]);
    $service->addCredit($client, 25);
    expect((float) $client->fresh()->credit)->toBe(35.0);
});

test('client service deducts credit', function () {
    $service = new ClientService();
    $client = Client::factory()->create(['credit' => 50]);
    $service->deductCredit($client, 30);
    expect((float) $client->fresh()->credit)->toBe(20.0);
});

test('client service does not go negative on credit', function () {
    $service = new ClientService();
    $client = Client::factory()->create(['credit' => 10]);
    $service->deductCredit($client, 50);
    expect((float) $client->fresh()->credit)->toBe(0.0);
});

// TicketService tests
test('ticket service creates ticket with unique tid', function () {
    $service = new TicketService();
    $client = Client::factory()->create();
    $dept = TicketDepartment::factory()->create();
    $ticket = $service->createTicket([
        'department_id' => $dept->id,
        'client_id' => $client->id,
        'name' => 'John Doe',
        'email' => 'john@test.com',
        'title' => 'Test ticket',
        'message' => 'Test message',
    ]);
    expect($ticket->tid)->not->toBeNull()
        ->and(strlen($ticket->tid))->toBe(6)
        ->and($ticket->status)->toBe('Open');
});

test('ticket service admin reply changes status to answered', function () {
    $service = new TicketService();
    $client = Client::factory()->create();
    $dept = TicketDepartment::factory()->create();
    $ticket = $service->createTicket([
        'department_id' => $dept->id, 'client_id' => $client->id,
        'name' => 'Test', 'email' => 'test@test.com',
        'title' => 'Test', 'message' => 'Test',
    ]);
    $service->addReply($ticket, ['admin' => 'Admin User', 'message' => 'Reply from admin']);
    expect($ticket->fresh()->status)->toBe('Answered');
});

test('ticket service client reply changes status to customer-reply', function () {
    $service = new TicketService();
    $client = Client::factory()->create();
    $dept = TicketDepartment::factory()->create();
    $ticket = $service->createTicket([
        'department_id' => $dept->id, 'client_id' => $client->id,
        'name' => 'Test', 'email' => 'test@test.com',
        'title' => 'Test', 'message' => 'Test',
    ]);
    $ticket->update(['status' => 'Answered']);
    $service->addReply($ticket, ['client_id' => $client->id, 'message' => 'Client reply']);
    expect($ticket->fresh()->status)->toBe('Customer-Reply');
});

test('ticket service closes ticket', function () {
    $service = new TicketService();
    $client = Client::factory()->create();
    $dept = TicketDepartment::factory()->create();
    $ticket = Ticket::factory()->create(['client_id' => $client->id, 'department_id' => $dept->id]);
    $service->closeTicket($ticket);
    expect($ticket->fresh()->status)->toBe('Closed');
});

// DomainService tests
test('domain service registers domain', function () {
    $service = new DomainService();
    $client = Client::factory()->create();
    $domain = $service->registerDomain($client, [
        'domain' => 'example.com',
        'registrar' => 'custom',
        'registration_period' => 1,
    ]);
    expect($domain->status)->toBe('pending')
        ->and($domain->type)->toBe('Register')
        ->and($domain->client_id)->toBe($client->id);
});

test('domain service cancels domain', function () {
    $service = new DomainService();
    $domain = Domain::factory()->create();
    $service->cancelDomain($domain);
    expect($domain->fresh()->status)->toBe('cancelled');
});

// TransactionService tests
test('transaction service records payment', function () {
    $service = new TransactionService();
    $client = Client::factory()->create();
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'total' => 100]);
    $txn = $service->recordPayment($invoice, ['gateway' => 'paypal', 'amount' => 100]);
    expect((float) $txn->amount_in)->toBe(100.0)
        ->and($txn->invoice_id)->toBe($invoice->id);
});

test('transaction service records refund', function () {
    $service = new TransactionService();
    $client = Client::factory()->create();
    $invoice = Invoice::factory()->create(['client_id' => $client->id]);
    $txn = $service->recordRefund($invoice, 50, 'Customer request');
    expect((float) $txn->amount_in)->toBe(0.0)
        ->and((float) $txn->amount_out)->toBe(50.0);
});

// AffiliateService tests
test('affiliate service activates affiliate', function () {
    $service = new AffiliateService();
    $client = Client::factory()->create();
    $affiliate = $service->activateAffiliate($client);
    expect($affiliate->client_id)->toBe($client->id)
        ->and((float) $affiliate->balance)->toEqual(0.0);
});
