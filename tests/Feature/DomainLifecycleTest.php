<?php

use App\Models\Client;
use App\Models\Domain;
use App\Models\DomainPricing;
use App\Services\InvoiceGenerationService;
use Illuminate\Support\Facades\Mail;

/**
 * What happens to a domain once its expiry date passes.
 *
 * Nothing did. The status enum has grace, redemption and expired, and every
 * TLD carries a grace period and a redemption period an operator can set —
 * and no code read any of it. A domain stayed "active" for ever: the customer
 * was shown an active domain that had actually lapsed at the registrar, and
 * the renewal run kept invoicing it, because it only bills domains that are
 * active.
 */
function lapsedDomain(int $daysAgo, array $periods = ['grace_period' => 30, 'redemption_grace_period' => 30]): Domain
{
    DomainPricing::updateOrCreate(
        ['extension' => '.com'],
        array_merge([
            'register_price' => 10,
            'renew_price' => 12,
            'transfer_price' => 10,
            'min_years' => 1,
            'max_years' => 5,
            'enabled' => true,
            'sort_order' => 0,
        ], $periods)
    );

    return Domain::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'domain' => 'lapsed-'.$daysAgo.'.com',
        'registrar' => 'Manual',
        'status' => 'active',
        'expiry_date' => now()->subDays($daysAgo),
        'next_due_date' => now()->subDays($daysAgo),
        'recurring_amount' => 12,
        'registration_period' => 1,
    ]);
}

test('a domain just past its expiry is in its grace period', function () {
    $domain = lapsedDomain(5);

    $this->artisan('pnlcs:domain-sync')->assertSuccessful();

    expect($domain->fresh()->status)->toBe('grace');
});

test('a domain still in grace is billed, because it can still be renewed', function () {
    Mail::fake();
    $domain = lapsedDomain(5);

    $this->artisan('pnlcs:domain-sync')->assertSuccessful();

    $summary = app(InvoiceGenerationService::class)->generateDueInvoices();

    expect($summary['generated'] ?? 0)->toBeGreaterThan(0);
});

test('past the grace period it is in redemption', function () {
    $domain = lapsedDomain(40);

    $this->artisan('pnlcs:domain-sync')->assertSuccessful();

    expect($domain->fresh()->status)->toBe('redemption');
});

test('a domain in redemption is not invoiced at the ordinary price', function () {
    Mail::fake();
    lapsedDomain(40);

    $this->artisan('pnlcs:domain-sync')->assertSuccessful();

    $summary = app(InvoiceGenerationService::class)->generateDueInvoices();

    expect($summary['generated'] ?? 0)->toBe(0);
});

test('past redemption it is expired', function () {
    $domain = lapsedDomain(120);

    $this->artisan('pnlcs:domain-sync')->assertSuccessful();

    expect($domain->fresh()->status)->toBe('expired');
});

test('a domain with no expiry date is left alone', function () {
    $domain = lapsedDomain(5);
    $domain->update(['expiry_date' => null]);

    $this->artisan('pnlcs:domain-sync')->assertSuccessful();

    expect($domain->fresh()->status)->toBe('active');
});

test('a domain that has not expired is left alone', function () {
    $domain = lapsedDomain(5);
    $domain->update(['expiry_date' => now()->addMonths(6)]);

    $this->artisan('pnlcs:domain-sync')->assertSuccessful();

    expect($domain->fresh()->status)->toBe('active');
});
