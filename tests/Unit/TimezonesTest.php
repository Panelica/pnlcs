<?php

use App\Support\Timezones;

it('offers every canonical timezone in the same set display_tz accepts', function () {
    $zones = Timezones::all();

    expect($zones)->toHaveCount(count(timezone_identifiers_list(DateTimeZone::ALL)))
        ->and($zones)->toContain('Europe/Warsaw', 'America/New_York', 'UTC')
        ->and($zones)->toBe(array_values(array_unique($zones)));
});

it('groups zones by region and keeps the utc entry', function () {
    $groups = Timezones::grouped();

    expect($groups)->toHaveKeys(['Africa', 'America', 'Asia', 'Europe', 'UTC'])
        ->and($groups['Europe'])->toContain('Europe/Warsaw')
        ->and($groups['UTC'])->toBe(['UTC']);
});
