<?php

namespace Shearerline\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Shearerline\ShearerlineServiceProvider;

class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ShearerlineServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Shearerline' => \Shearerline\Facades\Shearerline::class,
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
