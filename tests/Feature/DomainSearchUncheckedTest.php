<?php

use App\Models\DomainPricing;
use App\Services\WhoisLookup;

/**
 * What the search says when the registry does not answer.
 *
 * An unanswered lookup was read as "available": the name came back green on
 * the search page, with a price and an add-to-cart button beside it and only a
 * small grey note to say it had not been confirmed. The suggested endings did
 * not carry even that. A customer could buy a name nobody had checked and find
 * out it was taken when the registration failed.
 */
function fakeWhois(bool $checked, bool $available = false): void
{
    app()->instance(WhoisLookup::class, new class($checked, $available) extends WhoisLookup
    {
        public function __construct(private bool $checkedResult, private bool $availableResult) {}

        public function check(string $domain, ?string $server): array
        {
            return [
                'available' => $this->availableResult,
                'checked' => $this->checkedResult,
                'response' => '',
            ];
        }
    });
}

function searchablePricing(): void
{
    DomainPricing::updateOrCreate(['extension' => '.com'], [
        'register_price' => 12.99,
        'transfer_price' => 12.99,
        'renew_price' => 14.99,
        'min_years' => 1,
        'max_years' => 10,
        'sort_order' => 1,
        'enabled' => true,
    ]);
}

it('does not report a name it could not check as available', function () {
    searchablePricing();
    fakeWhois(checked: false);

    $primary = $this->post(route('client.domain.check'), ['domain' => 'unchecked-example.com'])
        ->assertOk()
        ->json('primary');

    expect($primary['available'])->toBeFalse()
        ->and($primary['checked'])->toBeFalse();
});

it('reports a free name as available', function () {
    searchablePricing();
    fakeWhois(checked: true, available: true);

    $primary = $this->post(route('client.domain.check'), ['domain' => 'free-example.com'])
        ->assertOk()
        ->json('primary');

    expect($primary['available'])->toBeTrue()
        ->and($primary['checked'])->toBeTrue();
});

it('does not offer to sell a name it could not check', function () {
    searchablePricing();
    fakeWhois(checked: false);

    $html = $this->get(route('client.domain.search', ['domain' => 'unchecked-example.com']))
        ->assertOk()
        ->getContent();

    expect($html)->toContain(__('client.domain_search.could_not_check'))
        ->and(substr_count($html, 'cart/add-domain'))->toBe(0);
});

it('offers to sell a name the registry says is free', function () {
    searchablePricing();
    fakeWhois(checked: true, available: true);

    $html = $this->get(route('client.domain.search', ['domain' => 'free-example.com']))
        ->assertOk()
        ->getContent();

    expect($html)->toContain(__('client.domain_search.available'))
        ->and(substr_count($html, 'cart/add-domain'))->toBeGreaterThan(0);
});
