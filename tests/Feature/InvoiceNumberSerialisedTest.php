<?php

use App\Models\Client;
use App\Services\InvoiceService;

/*
 * The next invoice number is "the highest issued so far, plus one", read and
 * written in two steps. Two invoices raised at the same moment - a webhook
 * landing while the renewal run is going - could both read the same highest
 * number and both issue it. Numbering is now serialised through a named
 * database lock held from the read to the insert.
 */

function secondConnection(): PDO
{
    $c = config('database.connections.mysql');

    return new PDO("mysql:host={$c['host']};port={$c['port']};dbname={$c['database']}", $c['username'], $c['password']);
}

test('invoice numbering waits for whoever is numbering an invoice right now', function () {
    $other = secondConnection();
    expect((int) $other->query("SELECT GET_LOCK('".InvoiceService::NUMBER_LOCK."', 0)")->fetchColumn())->toBe(1);

    InvoiceService::$numberLockSeconds = 1;
    $client = Client::factory()->create(['tax_exempt' => true]);

    try {
        expect(fn () => app(InvoiceService::class)->createInvoice($client, [['description' => 'x', 'amount' => 1]]))
            ->toThrow(RuntimeException::class);
    } finally {
        $other->query("SELECT RELEASE_LOCK('".InvoiceService::NUMBER_LOCK."')");
        InvoiceService::$numberLockSeconds = 15;
    }

    // With the lock released the same call goes through and the lock is
    // handed back afterwards, so nothing waits on us.
    $invoice = app(InvoiceService::class)->createInvoice($client, [['description' => 'x', 'amount' => 1]]);
    expect($invoice->invoice_num)->not->toBe('')
        ->and((int) $other->query("SELECT GET_LOCK('".InvoiceService::NUMBER_LOCK."', 0)")->fetchColumn())->toBe(1);
    $other->query("SELECT RELEASE_LOCK('".InvoiceService::NUMBER_LOCK."')");
});
