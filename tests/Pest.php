<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');
pest()->extend(TestCase::class)->in('Unit');

/*
 * Every test starts from the same database.
 *
 * Without this the suite ran against whatever the last run left behind, so a
 * test could pass because an earlier one seeded a row and fail on its own. It
 * also made switching branches look like product breakage: rows and schema
 * carried over from the previous branch's migration set.
 *
 * Transactions rather than a full refresh: the schema is migrated once, and each
 * test is rolled back afterwards. A per-test rebuild costs the suite half an
 * hour instead of a few minutes, which nobody would run.
 */
pest()->use(DatabaseTransactions::class)->in('Feature', 'Unit');
