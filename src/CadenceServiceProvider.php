<?php

namespace DirectoryTree\Cadence;

use Cron\CronExpression;
use DirectoryTree\Cadence\Commands\RunDueSchedules;
use DirectoryTree\Cadence\Drivers\CronSchedule;
use DirectoryTree\Cadence\Drivers\RecurrSchedule;
use DirectoryTree\Cadence\Drivers\RruleSchedule;
use Illuminate\Support\ServiceProvider;
use Recurr\Rule;
use RRule\RRule;

class CadenceServiceProvider extends ServiceProvider
{
    /**
     * Register package services.
     */
    public function register(): void
    {
        if (class_exists(CronExpression::class)) {
            Cadence::register('cron', CronSchedule::class);
        }

        if (class_exists(RRule::class)) {
            Cadence::register('rrule', RruleSchedule::class);
        }

        if (class_exists(Rule::class)) {
            Cadence::register('recurr', RecurrSchedule::class);
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
