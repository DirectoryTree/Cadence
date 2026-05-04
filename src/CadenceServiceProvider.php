<?php

namespace DirectoryTree\Cadence;

use DirectoryTree\Cadence\Commands\RunDueSchedules;
use DirectoryTree\Cadence\Drivers\CronSchedule;
use DirectoryTree\Cadence\Drivers\RecurrSchedule;
use DirectoryTree\Cadence\Drivers\RruleSchedule;
use Illuminate\Support\ServiceProvider;

class CadenceServiceProvider extends ServiceProvider
{
    /**
     * Register package services.
     */
    public function register(): void
    {
        Schedule::driver('cron', CronSchedule::class);

        if (class_exists(\RRule\RRule::class)) {
            Schedule::driver('rrule', RruleSchedule::class);
        }

        if (class_exists(\Recurr\Rule::class)) {
            Schedule::driver('recurr', RecurrSchedule::class);
        }
    }

    /**
     * Bootstrap package services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                RunDueSchedules::class,
            ]);

            $this->publishesMigrations([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ]);
        }
    }
}
