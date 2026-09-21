<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Gateways\BankTransfer\BankTransferModule;

/**
 * A gateway's payment form is printed raw, so what it concatenates must be safe.
 *
 * resources/views/client/invoices/show.blade.php:195 prints the gateway form
 * with {!! !!}, which it has to: the form is markup. BankTransferModule built
 * that markup by concatenating sixteen __() values, and escaped only the data
 * around them. Those values come from dynamic_translations, which the admin
 * translation editor writes with no sanitising, so a stored <script> in any of
 * the sixteen labels ran in a paying customer's browser.
 *
 * The guard added for the two marketing pages could not help here: it allows a
 * small set of inline tags through on purpose, and a payment form is not inline
 * markup. The answer is the one the module already used for its data - escape
 * the value - applied to the translations as well. None of these keys ships
 * with markup, so nothing a customer reads changes.
 */
function bankTransferInvoice(): Invoice
{
    $client = Client::factory()->create();
    $user = User::factory()->create();
    $user->clients()->attach($client->id);

    return Invoice::factory()->create([
        'client_id' => $client->id,
        'status' => 'unpaid',
        'total' => 100,
    ]);
}

/** A shop with one bank account set up, which is what reaches the full form. */
function bankTransferConfigured(): void
{
    foreach (['bank_name' => 'Test Bank', 'iban' => 'TR000000000000000000000000'] as $setting => $value) {
        DB::table('gateway_settings')->updateOrInsert(
            ['gateway' => 'banktransfer', 'setting' => $setting],
            ['value' => encrypt($value), 'created_at' => now(), 'updated_at' => now()]
        );
    }
}

function storeTranslation(string $group, string $key, string $value): void
{
    DB::table('dynamic_translations')->insert([
        'language' => 'en', 'group' => $group, 'key' => $key, 'value' => $value,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    Cache::flush();
}

test('a script stored in a gateway label never reaches the payment form', function () {
    bankTransferConfigured();
    storeTranslation('messages', 'banktransfer.details_title', 'Details<script>alert(1)</script>');

    $html = app(BankTransferModule::class)->getPaymentForm(bankTransferInvoice());

    expect($html)->not->toContain('<script>')
        ->and($html)->toContain('&lt;script&gt;');
});

test('every label the form prints is escaped, not only the first', function () {
    // The one that ships inside a <strong>, and the one inside an alert: both
    // were concatenated raw and both are reached on ordinary invoices.
    bankTransferConfigured();
    storeTranslation('messages', 'banktransfer.reference', '<img src=x onerror=alert(1)>');
    storeTranslation('messages', 'banktransfer.amount', '"><script>alert(2)</script>');

    $html = app(BankTransferModule::class)->getPaymentForm(bankTransferInvoice());

    // The text "onerror=" survives escaping and is harmless as text; what must
    // not survive is the tag it would have been an attribute of.
    expect($html)->not->toContain('<img')
        ->and($html)->toContain('&lt;img')
        ->and($html)->not->toContain('<script>')
        ->and($html)->toContain('&lt;script&gt;');
});

test('the label a shop with no bank accounts shows is escaped too', function () {
    // Reached through a different branch of the method, so it needs its own case.
    DB::table('gateway_settings')->where('gateway', 'banktransfer')->delete();
    storeTranslation('messages', 'banktransfer.no_accounts', 'None<script>alert(3)</script>');

    $html = app(BankTransferModule::class)->getPaymentForm(bankTransferInvoice());

    expect($html)->not->toContain('<script>');
});

test('the form still reads correctly when nobody is attacking it', function () {
    bankTransferConfigured();

    $html = app(BankTransferModule::class)->getPaymentForm(bankTransferInvoice());

    // The markup the module builds itself must survive: escaping the
    // translations must not have escaped the table around them.
    expect($html)->toContain('<tr>')
        ->and($html)->toContain('<th scope="row">')
        ->and($html)->not->toContain('&lt;tr&gt;');
});
