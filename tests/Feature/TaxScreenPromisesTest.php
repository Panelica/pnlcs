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
            'level' => 1,
            'name' => 'VAT',
            'country' => 'GB',
            'tax_rate' => 20,
        ])->assertRedirect();

    expect(TaxRule::where('name', 'VAT')->where('country', 'GB')->exists())->toBeTrue();
});
