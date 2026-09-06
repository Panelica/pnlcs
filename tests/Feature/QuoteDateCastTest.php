<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Quote;

/*
 * The quotes list called $quote->valid_until->format() straight on the value.
 * The Quote model cast neither of its two DATE columns, so Eloquent handed
 * back a raw string and the page died with "member function format() on
 * string" the moment a single quote existed. The other quote views survived
 * only because they defensively Carbon::parse()'d first.
 */

function quoteAdmin(): Admin
{
    return Admin::factory()->create([
        'role_id' => AdminRole::factory()->fullAdmin()->create()->id,
    ]);
}

it('casts the quote date columns to Carbon', function () {
    $quote = Quote::factory()->create();

    expect($quote->fresh()->valid_until)->toBeInstanceOf(\Carbon\CarbonInterface::class)
        ->and($quote->fresh()->date)->toBeInstanceOf(\Carbon\CarbonInterface::class);
});

it('renders the config quotes list with a quote present', function () {
    Quote::factory()->create();

    $this->actingAs(quoteAdmin(), 'admin')
        ->get(route('admin.config.quotes'))
        ->assertOk();
});

it('renders the quotes index with a quote present', function () {
    Quote::factory()->create();

    $this->actingAs(quoteAdmin(), 'admin')
        ->get(route('admin.quotes.index'))
        ->assertOk();
});
