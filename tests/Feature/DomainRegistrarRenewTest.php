<?php

use App\Contracts\RegistrarModuleInterface;
use App\Models\Client;
use App\Models\Domain;
use App\Models\Invoice;
use App\Services\DomainService;
use App\Services\Module\ModuleRegistry;
use App\Services\PaymentService;

/**
 * Registrar renewal wiring + the DomainService Carbon double-add fix.
 *
 * DomainService::renewDomain now performs the real registrar renew() API call
 * (the module advances the dates on success) and, when no module is configured
 * or the call fails, advances billing dates locally exactly once — the previous
 * code added the interval twice, leaving next_due a full period past expiry.
 */

function fakeRegistrar(string $name = 'testreg'): \Mockery\MockInterface
{
    $fake = Mockery::mock(RegistrarModuleInterface::class);
    $fake->shouldReceive('renew')->once()->andReturnUsing(function ($domain, $years) {
        $new = $domain->expiry_date->copy()->addYears($years);
        $domain->update(['expiry_date' => $new, 'next_due_date' => $new]);
        return ['success' => true, 'message' => 'renewed'];
    });
    app()->instance(RegistrarModuleInterface::class, $fake);
    app(ModuleRegistry::class)->registerRegistrar($name, RegistrarModuleInterface::class);
    return $fake;
}

it('calls the registrar renew API and advances dates exactly once', function () {
    fakeRegistrar();

    $client = Client::factory()->create();
    $domain = Domain::factory()->create([
        'client_id' => $client->id, 'registrar' => 'testreg',
        'registration_period' => 2,
        'expiry_date' => now()->addDays(3), 'next_due_date' => now()->addDays(3),
    ]);
    $old = $domain->expiry_date->copy();

    app(DomainService::class)->renewDomain($domain, 2);

    $domain->refresh();
    expect($domain->expiry_date->toDateString())->toBe($old->copy()->addYears(2)->toDateString())
        ->and($domain->next_due_date->toDateString())->toBe($old->copy()->addYears(2)->toDateString());
});

it('advances dates locally without double-add when no registrar module is configured', function () {
    $client = Client::factory()->create();
    $domain = Domain::factory()->create([
        'client_id' => $client->id, 'registrar' => 'nonexistent-reg',
        'registration_period' => 1,
        'expiry_date' => now()->addDays(3), 'next_due_date' => now()->addDays(3),
    ]);
    $old = $domain->expiry_date->copy();

    app(DomainService::class)->renewDomain($domain, 1);

    $domain->refresh();
    // Old bug: next_due = expiry + 2 years. Both must be exactly +1 year.
    expect($domain->expiry_date->toDateString())->toBe($old->copy()->addYear()->toDateString())
        ->and($domain->next_due_date->toDateString())->toBe($old->copy()->addYear()->toDateString());
});

it('invokes the registrar renew API when a domain renewal invoice is paid', function () {
    fakeRegistrar();

    $client = Client::factory()->create();
    $domain = Domain::factory()->create([
        'client_id' => $client->id, 'registrar' => 'testreg', 'status' => 'active',
        'registration_period' => 1, 'recurring_amount' => 15.00,
        'expiry_date' => now()->addDays(5), 'next_due_date' => now()->addDays(5),
    ]);

    $this->artisan('pnlcs:generate-invoices');
    $invoice = Invoice::whereHas('items', fn ($q) => $q->where('type', 'Domain')->where('rel_id', $domain->id))->first();
    expect($invoice)->not->toBeNull();

    // Mockery ->once() (set in fakeRegistrar) verifies the registrar was called
    // as part of the paid-renewal flow.
    app(PaymentService::class)->applyPayment($invoice, 'banktransfer', 'TXN-REG', (float) $invoice->total);
});
