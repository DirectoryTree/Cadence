<?php

namespace DirectoryTree\Cadence\Tests;

use DirectoryTree\Cadence\CadenceServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            CadenceServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
    }

    protected function createScheduleTables(): void
    {
        (require __DIR__.'/../database/migrations/create_schedules_table.php.stub')->up();
        (require __DIR__.'/../database/migrations/update_schedules_table_add_disabled_at.php.stub')->up();
    }
}
