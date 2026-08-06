<?php

use App\Contracts\RegistrarModuleInterface;
use App\Models\Client;
use App\Models\Domain;
use App\Models\Invoice;
use App\Services\DomainService;
use App\Services\Module\ModuleRegistry;
use App\Services\NotificationService;
use App\Services\PaymentService;

/**
 * A renewal the registrar refused.
 *
 * When the registrar says no - out of funds at the registry, the domain is
 * locked, the account is suspended - the panel advanced the expiry date by a
 * year anyway and wrote a line in the log. Nothing else happened.
 *
 * So the panel shows a domain registered until next year while the registry
 * has it expiring this month. No reminder goes out, because as far as the
 * panel is concerned there is nothing due for a year, and the domain lapses
 * with the customer's site on it.
 */
function refusingRegistrar(string $name = 'refusereg', string $message = 'Insufficient funds'): void
{
    $fake = Mockery::mock(RegistrarModuleInterface::class);
    $fake->shouldReceive('renew')->andReturn(['success' => false, 'message' => $message]);
    app()->instance(RegistrarModuleInterface::class, $fake);
    app(ModuleRegistry::class)->registerRegistrar($name, RegistrarModuleInterface::class);
}

function throwingRegistrar(string $name = 'throwreg'): void
{
    $fake = Mockery::mock(RegistrarModuleInterface::class);
    $fake->shouldReceive('renew')->andThrow(new RuntimeException('connection reset'));
    app()->instance(RegistrarModuleInterface::class, $fake);
    app(ModuleRegistry::class)->registerRegistrar($name, RegistrarModuleInterface::class);
}

function refusedDomainOn(string $registrar): Domain
{
    return Domain::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'registrar' => $registrar,
        'registration_period' => 1,
        'expiry_date' => now()->addDays(5),
        'next_due_date' => now()->addDays(5),
        'status' => 'active',
    ]);
}

/** Capture what the panel told the operator. */
function renewalAlerts(): ArrayObject
{
    $seen = new ArrayObject;

    $spy = Mockery::mock(NotificationService::class);
    $spy->shouldReceive('dispatch')->andReturnUsing(function ($event, $data = []) use ($seen) {
        $seen[] = ['event' => $event] + $data;
    });

    app()->instance(NotificationService::class, $spy);

    return $seen;
}

it('does not move the expiry date when the registrar refuses', function () {
    refusingRegistrar();
    renewalAlerts();

    $domain = refusedDomainOn('refusereg');
    $before = $domain->expiry_date->toDateString();

    app(DomainService::class)->renewDomain($domain, 1);

    expect($domain->fresh()->expiry_date->toDateString())->toBe($before);
    expect($domain->fresh()->next_due_date->toDateString())->toBe($before);
});

it('does not move the expiry date when the registrar cannot be reached', function () {
    throwingRegistrar();
    renewalAlerts();

    $domain = refusedDomainOn('throwreg');
    $before = $domain->expiry_date->toDateString();

    app(DomainService::class)->renewDomain($domain, 1);

    expect($domain->fresh()->expiry_date->toDateString())->toBe($before);
});

it('tells somebody the renewal did not happen', function () {
    refusingRegistrar('alertreg', 'Domain is locked');
    $alerts = renewalAlerts();

    $domain = refusedDomainOn('alertreg');

    app(DomainService::class)->renewDomain($domain, 1);

    expect($alerts->count())->toBe(1);
    expect($alerts[0]['event'])->toBe('domain.renew_failed');
    expect($alerts[0]['message'])->toContain($domain->domain);
    expect($alerts[0]['message'])->toContain('Domain is locked');
});

it('still advances a domain the panel does not register through anyone', function () {
    renewalAlerts();

    $domain = refusedDomainOn('nobody-registers-here');
    $before = $domain->expiry_date->copy();

    app(DomainService::class)->renewDomain($domain, 1);

    expect($domain->fresh()->expiry_date->toDateString())->toBe($before->copy()->addYear()->toDateString());
});

it('does not bill again for a year the customer has already paid for', function () {
    refusingRegistrar('rebillreg', 'Insufficient funds');
    renewalAlerts();

    $domain = refusedDomainOn('rebillreg');
    $domain->update(['recurring_amount' => 15.00]);

    $this->artisan('pnlcs:generate-invoices');

    $invoice = Invoice::whereHas('items', fn ($q) => $q->where('type', 'Domain')->where('rel_id', $domain->id))->firstOrFail();

    // Paying it calls the registrar, which refuses; the dates stay put.
    app(PaymentService::class)->applyPayment($invoice, 'banktransfer', 'TXN-REFUSED', (float) $invoice->total);

    expect($domain->fresh()->next_due_date->toDateString())->toBe($domain->next_due_date->toDateString());

    // The next run must not raise a second invoice for the same year.
    $this->artisan('pnlcs:generate-invoices');

    $count = Invoice::whereHas('items', fn ($q) => $q->where('type', 'Domain')->where('rel_id', $domain->id))->count();

    expect($count)->toBe(1);
});
