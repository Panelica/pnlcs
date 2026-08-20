<?php

use App\Models\Client;
use App\Models\Domain;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * The registrar lock and the EPP code.
 *
 * Both exist on every registrar module — getEPPCode(), getLockStatus(),
 * toggleLock() — and the customer's domain page used neither. It made an EPP
 * code up out of an md5 of the domain name and its row id, and it "locked" the
 * domain by writing the word into the status column, which is where the
 * domain's lifecycle lives.
 */
function customerDomain(string $registrar = 'Namecheap', string $status = 'active'): array
{
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);

    $domain = Domain::factory()->create([
        'client_id' => $client->id,
        'domain' => 'locked-test.com',
        'registrar' => $registrar,
        'status' => $status,
        'notes' => 'anything',
    ]);

    return compact('user', 'domain');
}

test('locking a domain does not overwrite what the domain is', function () {
    Http::fake(['*' => Http::response('<?xml version="1.0"?><ApiResponse Status="OK"><CommandResponse><DomainSetRegistrarLockResult IsSuccess="true" /></CommandResponse></ApiResponse>', 200)]);
    $fx = customerDomain();

    $this->actingAs($fx['user'])->post(route('client.domains.lock', $fx['domain']))->assertRedirect();

    // status says whether the domain is active, expired, cancelled. It is not
    // somewhere to keep a lock flag.
    expect(strtolower($fx['domain']->fresh()->status))->toBe('active');
});

test('unlocking an expired domain does not bring it back to life', function () {
    Http::fake(['*' => Http::response('<?xml version="1.0"?><ApiResponse Status="OK"></ApiResponse>', 200)]);
    $fx = customerDomain('Namecheap', 'expired');

    $this->actingAs($fx['user'])->post(route('client.domains.lock', $fx['domain']))->assertRedirect();

    // Setting it active would put it back in front of the renewal generator.
    expect(strtolower($fx['domain']->fresh()->status))->toBe('expired');
});

test('the lock reaches the registrar', function () {
    Http::fake(['*' => Http::response('<?xml version="1.0"?><ApiResponse Status="OK"><CommandResponse><DomainSetRegistrarLockResult IsSuccess="true" /></CommandResponse></ApiResponse>', 200)]);
    $fx = customerDomain();

    $this->actingAs($fx['user'])->post(route('client.domains.lock', $fx['domain']))->assertRedirect();

    Http::assertSent(fn ($request) => str_contains(strtolower($request->url().json_encode($request->data())), 'registrarlock'));
});

test('the EPP code comes from the registrar, not from a hash of the domain name', function () {
    Http::fake(['*' => Http::response('<?xml version="1.0"?><ApiResponse Status="OK"></ApiResponse>', 200)]);
    $fx = customerDomain();

    $response = $this->actingAs($fx['user'])->getJson(route('client.domains.epp', $fx['domain']));

    $response->assertOk();

    $fabricated = md5($fx['domain']->domain.$fx['domain']->id);

    expect($response->json('epp_code'))->not->toBe($fabricated);
});

test('a domain with no registrar module says so instead of inventing a code', function () {
    $fx = customerDomain('Manual');

    $response = $this->actingAs($fx['user'])->getJson(route('client.domains.epp', $fx['domain']));

    $response->assertOk();

    expect($response->json('epp_code'))->not->toBe(md5($fx['domain']->domain.$fx['domain']->id));
});
