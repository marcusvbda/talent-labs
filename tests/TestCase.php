<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public const string TESTING_DATABASE = 'talent_labs_testing';

    /**
     * Safety net: refuse to run unless the default connection is PostgreSQL on
     * the dedicated testing database, so tests can never reach the dev database.
     */
    public static function assertTestingDatabase(): void
    {
        $connection = config('database.default');
        $database = config('database.connections.'.$connection.'.database');

        if ($connection !== 'pgsql' || $database !== self::TESTING_DATABASE) {
            throw new RuntimeException(sprintf(
                'Tests must run on the pgsql database "%s"; refusing to run against "%s" / "%s".',
                self::TESTING_DATABASE,
                $connection,
                $database,
            ));
        }
    }

    /**
     * RefreshDatabase migrates while traits are set up, before any Pest
     * beforeEach runs: guard here too (also covers class-based tests).
     *
     * @return array<class-string, class-string>
     */
    protected function setUpTraits()
    {
        static::assertTestingDatabase();

        return parent::setUpTraits();
    }
}
