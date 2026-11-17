<?php

use App\Contracts\RegistrarModuleInterface;
use App\Contracts\SyncsDomainData;
use App\Models\Admin;
use App\Models\Client;
use App\Models\Domain;
use App\Services\Module\ModuleRegistry;

/*
 * The admin Sync button and the nightly domains:sync read the same registry
 * answer but wrote different due dates: the button put the due date 14 days
 * before every expiry it read, overriding a due date billing had set apart,
 * while the nightly sync only moves a due date that was following the
 * expiry. Both now share DomainSyncChanges.
 */

function syncButtonRegistrar(string $expiry): void
{
    $fake = Mockery::mock(RegistrarModuleInterface::class.', '.SyncsDomainData::class);
    $fake->shouldReceive('syncDomain')->andReturn(['success' => true, 'expiry_date' => $expiry, 'status' => 'active']);
    $fake->shouldReceive('getModuleName')->andReturn('syncbutton');

    app()->instance('syncbutton.fake', $fake);
    app(ModuleRegistry::class)->registerRegistrar('syncbutton', 'syncbutton.fake');
}

function syncButtonDomain(string $expiry, string $due): Domain
{
    return Domain::create([
        'client_id' => Client::factory()->create()->id, 'domain' => 'sync-button.com', 'type' => 'Transfer',
        'registrar' => 'syncbutton', 'status' => 'active', 'registration_period' => 1,
        'registration_date' => '2020-01-01', 'expiry_date' => $expiry, 'next_due_date' => $due,
        'first_payment_amount' => 0, 'recurring_amount' => 10,
    ]);
}

function pressSync(Domain $domain): void
{
    test()->actingAs(Admin::factory()->create(), 'admin')
        ->post(route('admin.domains.sync', $domain))
        ->assertSessionHas('success');
}

test('a due date that followed the expiry follows the corrected expiry', function () {
    syncButtonRegistrar('2027-06-15');
    $domain = syncButtonDomain('2027-11-20', '2027-11-20');

    pressSync($domain);

    expect($domain->fresh()->expiry_date->toDateString())->toBe('2027-06-15')
        ->and($domain->fresh()->next_due_date->toDateString())->toBe('2027-06-15');
});

test('a due date set apart from the expiry stays where billing put it', function () {
    syncButtonRegistrar('2027-06-15');
    $domain = syncButtonDomain('2027-11-20', '2027-03-01');

    pressSync($domain);

    expect($domain->fresh()->expiry_date->toDateString())->toBe('2027-06-15')
        ->and($domain->fresh()->next_due_date->toDateString())->toBe('2027-03-01');
});

test('an unchanged expiry leaves the due date alone', function () {
    syncButtonRegistrar('2027-11-20');
    $domain = syncButtonDomain('2027-11-20', '2027-11-20');

    pressSync($domain);

    expect($domain->fresh()->next_due_date->toDateString())->toBe('2027-11-20')
        ->and($domain->fresh()->last_sync_status)->toBe('ok');
});
