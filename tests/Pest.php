<?php

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

pest()->extend(TestCase::class)
    ->use(DatabaseTransactions::class)
    ->in("Feature");
