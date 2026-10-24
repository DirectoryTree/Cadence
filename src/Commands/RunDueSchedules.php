<?php

namespace DirectoryTree\Cadence\Commands;

use DirectoryTree\Cadence\Events\ScheduleTriggered;
use DirectoryTree\Cadence\Schedule;
use Illuminate\Console\Command;
use Throwable;

class RunDueSchedules extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    public $signature = 'schedules:run';

    /**
     * The console command description.
     *
     * @var string
     */
    public $description = 'Dispatch events for due model schedules';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $now = now();

        Schedule::due($now)->eachById(function (Schedule $schedule) {
            try {
                $dispatchedAt = now();

                ScheduleTriggered::dispatch($schedule, $dispatchedAt);

                $schedule->advance($dispatchedAt);
            } catch (Throwable $e) {
                report($e);
            }
        });

        return self::SUCCESS;
    }
}
