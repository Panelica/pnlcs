<?php

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in("Feature");

expect()->extend("toBeOne", function () {
    return $this->toBe(1);
});
