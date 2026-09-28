<?php

use Tests\TestCase;

/*
 * Every Pest test boots the app. Safety net: abort unless the default
 * connection is PostgreSQL on the dedicated `talent_labs_testing` database, so
 * a misconfigured environment can never reach the developer's database.
 * RefreshDatabase runs the same check before migrating (TestCase).
 */
pest()->extend(TestCase::class)
    ->beforeEach(function (): void {
        TestCase::assertTestingDatabase();
    })
    ->in('Feature', 'Unit');
