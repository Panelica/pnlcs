<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\KsefInvoice;
use App\Services\InvoicePdfService;
use Illuminate\Support\Facades\Mail;
use Modules\Ksef\KsefClient;
use Modules\Ksef\KsefService;

/**
 * When KSeF rejected an invoice, or had not confirmed it by the end of the
 * polling, the send reference was stored as the KSeF number. The customer was
 * mailed "e-Invoice (KSeF)" and the PDF printed the number and its QR code for
 * an invoice that was never accepted.
 */
function ksefRecord(): KsefInvoice
{
    $client = Client::factory()->create(['email' => 'buyer@example.pl']);
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'paid', 'subtotal' => 100, 'total' => 100]);

    return KsefInvoice::create(['invoice_id' => $invoice->id, 'status' => 'pending']);
}

function ksefServiceAnswering(array $result): KsefService
{
    $client = Mockery::mock(KsefClient::class);
    $client->shouldReceive('submit')->andReturn($result + ['success' => true, 'sent_at' => now()->toDateTimeString()]);

    return new KsefService($client, app(\App\Services\AddonManager::class));
}

it('does not mail the customer about an invoice KSeF rejected', function () {
    Mail::fake();
    $record = ksefRecord();

    ksefServiceAnswering(['status' => 'error', 'accepted' => false, 'ksef_number' => null, 'error_message' => 'Invalid NIP'])->send($record);

    Mail::assertNothingQueued();
    expect($record->fresh()->ksef_number)->toBeNull();
});

// What submit() used to hand back for an invoice KSeF had not confirmed: the
// send reference in the number's place. Only acceptance announces it.
it('does not mail the customer about an unconfirmed invoice that carries a number', function () {
    Mail::fake();
    $record = ksefRecord();

    ksefServiceAnswering(['status' => 'sent', 'accepted' => false, 'ksef_number' => 'REF-20261022-SEND'])->send($record);

    Mail::assertNothingQueued();
    expect($record->fresh()->accepted_at)->toBeNull();
});

it('mails the customer once KSeF accepted the invoice', function () {
    Mail::fake();
    $record = ksefRecord();

    ksefServiceAnswering(['status' => 'accepted', 'accepted' => true, 'ksef_number' => '1234567890-20261022-ABCDEF-01'])->send($record);

    Mail::assertQueued(\App\Mail\KsefInvoiceIssuedMail::class);
});

it('prints no KSeF block for a number that was never accepted', function () {
    $record = ksefRecord();
    $record->update(['ksef_number' => 'REF-NOT-A-KSEF-NUMBER', 'status' => 'error', 'accepted_at' => null]);

    $addons = Mockery::mock(\App\Services\AddonManager::class)->makePartial();
    $addons->shouldReceive('isActive')->with('ksef')->andReturn(true);
    app()->instance(\App\Services\AddonManager::class, $addons);

    expect(app(InvoicePdfService::class)->ksefInfo($record->invoice->fresh()))->toBeNull();
});
