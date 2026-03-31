<?php

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

pest()->extend(TestCase::class)
    ->use(DatabaseTransactions::class)
    ->in('Feature');

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});
