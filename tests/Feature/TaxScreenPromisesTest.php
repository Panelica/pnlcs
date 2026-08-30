<?php

use App\Models\Admin;
use App\Models\TaxRule;

/**
 * What the tax screen offers.
 *
 * The add and edit forms carried a "Compound tax" checkbox. There is no such
 * column on tax_rules, the controller never validated or stored it, and no
 * calculation has ever looked at it - the edit modal even read it back from a
 * field that does not exist, so the box was always empty again when the rule
 * was reopened.
 *
 * Compounding changes what a customer is charged: the second level would be
 * taken on the subtotal plus the first, rather than on the subtotal. Offering
 * the switch and doing nothing tells the operator they have set that up when
 * they have not.
 */
function taxAdmin(): Admin
{
    return Admin::factory()->create();
}

it('does not offer a compound switch that changes nothing', function () {
    $html = $this->actingAs(taxAdmin(), 'admin')
        ->get(route('admin.config.tax'))
        ->assertOk()
        ->getContent();

    expect($html)->not->toContain('name="compound"');
});

it('still creates a tax rule', function () {
    $this->actingAs(taxAdmin(), 'admin')
        ->post(route('admin.config.tax.store'), [
            'country' => 'GB',
            'rates' => [
                ['name' => 'VAT', 'tax_rate' => 20],
            ],
        ])->assertRedirect();

    expect(TaxRule::where('name', 'VAT')->where('country', 'GB')->exists())->toBeTrue();
});

it('stores a blank state as an empty string, not null', function () {
    // The state input is optional; left blank it arrives as null and the
    // controller must normalise it back to '' for the NOT NULL column.
    $this->actingAs(taxAdmin(), 'admin')
        ->post(route('admin.config.tax.store'), [
            'country' => 'GB',
            'rates' => [
                ['name' => 'VAT', 'tax_rate' => 23],
            ],
        ])->assertRedirect();

    $rule = TaxRule::where('name', 'VAT')->where('tax_rate', 23)->first();
    expect($rule)->not->toBeNull()
        ->and($rule->country)->toBe('GB')
        ->and($rule->state)->toBe('');
});

it('normalises blank state when updating a rule', function () {
    TaxRule::factory()->create(['country' => 'US', 'state' => 'TX', 'name' => 'Old', 'tax_rate' => 5]);

    $this->actingAs(taxAdmin(), 'admin')
        ->put(route('admin.config.tax.update', ['country' => 'US', 'state' => 'TX']), [
            'country' => 'US',
            'rates' => [
                ['name' => 'VAT', 'tax_rate' => 19],
            ],
        ])->assertRedirect();

    $rule = TaxRule::where('name', 'VAT')->where('tax_rate', 19)->first();
    expect($rule)->not->toBeNull()
        ->and($rule->country)->toBe('US')
        ->and($rule->state)->toBe('');
});
