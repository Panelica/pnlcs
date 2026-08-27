<?php

use App\Models\Promotion;

/**
 * End dates are inclusive everywhere else in this codebase - a quote is
 * actionable through its valid_until day, a suspension hold that ends today
 * still holds - but the promotion's date cast put its expiry at midnight, so
 * a code was refused for the whole of its own last day. The admin form even
 * allows start = expiration, a one-day promotion that could never be used.
 */
it('honours a promotion through the whole of its expiration day', function () {
    $promo = Promotion::create([
        'code' => 'LASTDAY', 'type' => 'percentage', 'value' => 10,
        'start_date' => now()->toDateString(),
        'expiration_date' => now()->toDateString(),
    ]);

    expect($promo->isValid())->toBeTrue();
});

it('still refuses a promotion the day after it expired', function () {
    $promo = Promotion::create([
        'code' => 'YESTERDAY', 'type' => 'percentage', 'value' => 10,
        'expiration_date' => now()->subDay()->toDateString(),
    ]);

    expect($promo->isValid())->toBeFalse();
});
