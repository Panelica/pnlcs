<?php

use App\Models\Domain;
use App\Services\Module\ModuleRegistry;
use Illuminate\Support\Facades\Http;

/**
 * ResellerClub looks every domain up by its order id first. The documented
 * answer of domains/orderid.json is the id as a bare integer, not a JSON
 * array or object; when that was not understood, renewals, nameserver
 * changes, the lock and the EPP code all failed with "order not found".
 */
function resellerClubDomain(): Domain
{
    return Domain::factory()->create([
        'domain' => 'orderid-test.com',
        'registrar' => 'ResellerClub',
        'status' => 'active',
        'expiry_date' => '2027-03-10',
        'next_due_date' => '2027-03-10',
        'nameservers' => json_encode(['old1.example.net', 'old2.example.net']),
    ]);
}

function resellerClubModule()
{
    return app(ModuleRegistry::class)->getRegistrarModule('ResellerClub');
}

function fakeResellerClub($orderIdAnswer = '12345678'): void
{
    Http::fake([
        '*domains/orderid.json*' => Http::response($orderIdAnswer, 200),
        '*domains/details.json*options=NsDetails*' => Http::response(['ns1' => 'NS1.Example.com', 'ns2' => 'ns2.example.com'], 200),
        '*domains/details.json*options=DomainSecretDetails*' => Http::response(['domsecret' => 'S3cret!'], 200),
        '*' => Http::response(['status' => 'Success'], 200),
    ]);
}

test('a renewal finds the order from the bare integer the API returns', function () {
    fakeResellerClub();
    $result = resellerClubModule()->renew(resellerClubDomain(), 1);

    expect($result['success'])->toBeTrue();
    Http::assertSent(fn ($request) => str_contains($request->url(), 'domains/renew.json')
        && ($request->data()['order-id'] ?? null) == '12345678');
});

test('the EPP code and nameserver change reach the order', function () {
    fakeResellerClub();
    $domain = resellerClubDomain();
    $module = resellerClubModule();

    expect($module->getEPPCode($domain))->toBe('S3cret!')
        ->and($module->saveNameservers($domain, ['ns1.new.net', 'ns2.new.net']))->toBeTrue()
        ->and($module->toggleLock($domain, true))->toBeTrue();
});

test('the nameservers are read as a list from the ns keys', function () {
    fakeResellerClub();
    expect(resellerClubModule()->getNameservers(resellerClubDomain()))
        ->toBe(['ns1.example.com', 'ns2.example.com']);
});

test('an unknown domain is still reported as not found', function () {
    fakeResellerClub(['status' => 'ERROR', 'message' => 'No order']);

    $result = resellerClubModule()->renew(resellerClubDomain(), 1);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('not found');
});
