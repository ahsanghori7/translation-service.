<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Runs before RefreshDatabase migrates, so a leaked environment variable
     * (e.g. DB_CONNECTION=mysql inside Docker) can never wipe a real database.
     *
     * @return array<class-string, class-string>
     */
    protected function setUpTraits()
    {
        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        if ($connection !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException(
                "Refusing to run tests against [{$connection}:{$database}]; expected in-memory SQLite.",
            );
        }

        return parent::setUpTraits();
    }
}
