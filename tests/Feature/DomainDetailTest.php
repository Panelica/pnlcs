<?php

use App\Models\Client;
use App\Models\Domain;
use App\Models\User;


function makeDomainClient(): array
{
    $user   = User::factory()->create();
    $client = Client::factory()->create(['email' => $user->email]);
    $user->clients()->attach($client->id, ['owner' => true, 'permissions' => null]);
    $domain = Domain::factory()->create([
        'client_id'   => $client->id,
        'domain'      => 'mysite_' . uniqid() . '.com',
        'status'      => 'active',
        'nameservers' => json_encode(['ns1' => 'ns1.example.com', 'ns2' => 'ns2.example.com']),
    ]);
    return [$user, $client, $domain];
}

test('unauthenticated user cannot view domain detail', function () {
    $domain = Domain::factory()->create();
    $this->get(route('client.domains.show', $domain))->assertRedirect();
});

test('authenticated user can view their domain detail', function () {
    [$user, $client, $domain] = makeDomainClient();

    $response = $this->actingAs($user)->get(route('client.domains.show', $domain));
    $response->assertStatus(200);
    $response->assertSee($domain->domain);
    $response->assertSee('Nameservers');
    $response->assertSee('EPP');
});

test('user cannot view another clients domain', function () {
    [$user, $client, $domain] = makeDomainClient();

    $otherClient = Client::factory()->create();
    $otherDomain = Domain::factory()->create(['client_id' => $otherClient->id]);

    $response = $this->actingAs($user)->get(route('client.domains.show', $otherDomain));
    $response->assertStatus(403);
});

test('user can update nameservers', function () {
    [$user, $client, $domain] = makeDomainClient();

    $response = $this->actingAs($user)->put(route('client.domains.nameservers', $domain), [
        'ns1' => 'ns1.cloudflare.com',
        'ns2' => 'ns2.cloudflare.com',
        'ns3' => '',
        'ns4' => '',
        'ns5' => '',
    ]);

    $response->assertRedirect(route('client.domains.show', $domain));
    $response->assertSessionHas('success');

    $domain->refresh();
    $ns = json_decode($domain->nameservers, true);
    expect($ns['ns1'])->toBe('ns1.cloudflare.com');
    expect($ns['ns2'])->toBe('ns2.cloudflare.com');
});

test('nameserver update requires ns1 and ns2', function () {
    [$user, $client, $domain] = makeDomainClient();

    $response = $this->actingAs($user)->put(route('client.domains.nameservers', $domain), [
        'ns1' => '',
        'ns2' => '',
    ]);

    $response->assertSessionHasErrors(['ns1', 'ns2']);
});

test('user cannot update nameservers of another clients domain', function () {
    [$user, $client, $domain] = makeDomainClient();

    $otherClient = Client::factory()->create();
    $otherDomain = Domain::factory()->create(['client_id' => $otherClient->id]);

    $response = $this->actingAs($user)->put(route('client.domains.nameservers', $otherDomain), [
        'ns1' => 'ns1.evil.com',
        'ns2' => 'ns2.evil.com',
    ]);

    $response->assertStatus(403);
});

test('user can toggle domain lock', function () {
    [$user, $client, $domain] = makeDomainClient();
    expect($domain->status)->toBe('active');

    $this->actingAs($user)->post(route('client.domains.lock', $domain));
    $domain->refresh();
    expect($domain->status)->toBe('locked');

    $this->actingAs($user)->post(route('client.domains.lock', $domain));
    $domain->refresh();
    expect($domain->status)->toBe('active');
});

test('user can toggle auto-renew', function () {
    [$user, $client, $domain] = makeDomainClient();
    $initialMethod = $domain->payment_method;

    $this->actingAs($user)->post(route('client.domains.autorenew', $domain));
    $domain->refresh();
    expect($domain->payment_method)->not->toBe($initialMethod);
});

test('epp code endpoint returns json for own domain', function () {
    [$user, $client, $domain] = makeDomainClient();

    $response = $this->actingAs($user)->get(route('client.domains.epp', $domain));
    $response->assertStatus(200);
    $response->assertJsonStructure(['epp_code']);
});

test('epp code endpoint is forbidden for other clients domain', function () {
    [$user, $client, $domain] = makeDomainClient();

    $otherClient = Client::factory()->create();
    $otherDomain = Domain::factory()->create(['client_id' => $otherClient->id]);

    $response = $this->actingAs($user)->get(route('client.domains.epp', $otherDomain));
    $response->assertStatus(403);
});

test('domains list only shows client owned domains', function () {
    [$user, $client, $domain] = makeDomainClient();

    $otherClient = Client::factory()->create();
    $otherDomain = Domain::factory()->create(['client_id' => $otherClient->id, 'domain' => 'other_' . uniqid() . '.com']);

    $response = $this->actingAs($user)->get(route('client.domains.index'));
    $response->assertStatus(200);
    $response->assertSee($domain->domain);
    $response->assertDontSee($otherDomain->domain);
});
