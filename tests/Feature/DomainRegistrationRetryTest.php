<?php

use App\Contracts\RegistrarModuleInterface;
use App\Models\Admin;
use App\Models\Client;
use App\Models\Domain;
use App\Services\Module\ModuleRegistry;

/*
 * A refused registration left a paid domain pending with no way to try again
 * once the cause was put right (a missing phone number, the registrar
 * balance, a module switched off): only the database could move it on. And
 * nothing an addon could listen to said it had happened.
 */

function retryRegistrar(string $name, bool $succeeds): ArrayObject
{
    $calls = new ArrayObject;

    $fake = Mockery::mock(RegistrarModuleInterface::class);
    $fake->shouldReceive('register')->andReturnUsing(function ($domain, $years, $params = []) use ($calls, $succeeds) {
        $calls[] = $params;
        if ($succeeds) {
            $domain->update(['status' => 'active']);
        }

        return ['success' => $succeeds, 'message' => $succeeds ? 'registered' : 'Phone is required'];
    });
    $fake->shouldReceive('getModuleName')->andReturn($name);

    app()->instance(RegistrarModuleInterface::class, $fake);
    app(ModuleRegistry::class)->registerRegistrar($name, RegistrarModuleInterface::class);

    return $calls;
}

function retryPendingDomain(string $registrar, string $type = 'Register'): Domain
{
    $client = Client::factory()->create(['phone_number' => '+44 20 7946 0000']);

    return Domain::create([
        'client_id' => $client->id, 'domain' => 'retry-me.com', 'type' => $type, 'registrar' => $registrar,
        'status' => 'pending', 'registration_period' => 1,
        'first_payment_amount' => 10, 'recurring_amount' => 10,
    ]);
}

test('the admin can send a pending domain to its registrar again', function () {
    $calls = retryRegistrar('retryok', succeeds: true);
    $domain = retryPendingDomain('retryok');

    $this->actingAs(Admin::factory()->create(), 'admin')
        ->post(route('admin.domains.register', $domain))
        ->assertSessionHas('success');

    expect($domain->fresh()->status)->toBe('active')
        ->and($calls)->toHaveCount(1)
        // No order behind it: the domain's own account is the registrant.
        ->and($calls[0]['phone'])->toBe('+44 20 7946 0000');
});

test('a retry the registrar refuses says why and leaves the domain pending', function () {
    retryRegistrar('retryno', succeeds: false);
    $domain = retryPendingDomain('retryno');

    $this->actingAs(Admin::factory()->create(), 'admin')
        ->post(route('admin.domains.register', $domain))
        ->assertSessionHas('error', fn ($m) => str_contains($m, 'Phone is required'));

    expect($domain->fresh()->status)->toBe('pending');
});

test('a refused registration runs AfterRegistrarRegistrationFailed', function () {
    retryRegistrar('retryhook', succeeds: false);
    $domain = retryPendingDomain('retryhook');
    $seen = [];
    add_hook('AfterRegistrarRegistrationFailed', 1, function ($vars) use (&$seen) {
        $seen[] = $vars;
    });

    app(\App\Services\OrderService::class)->retryDomainRegistration($domain);

    expect($seen)->toHaveCount(1)
        ->and($seen[0]['domainid'])->toBe($domain->id)
        ->and($seen[0]['error'])->toBe('Phone is required');
});

test('a transfer or an active domain is not offered a retry', function () {
    $calls = retryRegistrar('retryguard', succeeds: true);
    $transfer = retryPendingDomain('retryguard', 'Transfer');

    $this->actingAs(Admin::factory()->create(), 'admin')
        ->post(route('admin.domains.register', $transfer))
        ->assertSessionHas('error');

    $transfer->update(['type' => 'Register', 'status' => 'active']);
    $this->actingAs(Admin::factory()->create(), 'admin')
        ->post(route('admin.domains.register', $transfer))
        ->assertSessionHas('error');

    expect($calls)->toHaveCount(0);
});

test('the domain page shows the retry button only while the registration waits', function () {
    retryRegistrar('retryview', succeeds: true);
    $domain = retryPendingDomain('retryview');
    $admin = Admin::factory()->create();

    $this->actingAs($admin, 'admin')->get(route('admin.domains.show', $domain))
        ->assertOk()->assertSee(route('admin.domains.register', $domain), false);

    $domain->update(['status' => 'active']);
    $this->actingAs($admin, 'admin')->get(route('admin.domains.show', $domain))
        ->assertOk()->assertDontSee(route('admin.domains.register', $domain), false);
});
