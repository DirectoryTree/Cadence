<?php

namespace DirectoryTree\Cadence\Commands;

use DirectoryTree\Cadence\Events\ScheduleMissed;
use DirectoryTree\Cadence\Events\ScheduleTriggered;
use DirectoryTree\Cadence\Schedule;
use Illuminate\Console\Command;

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
    public $description = 'Dispatch events for due and missed model schedules';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $now = now();

        Schedule::due($now)->each(function (Schedule $schedule) use ($now) {
            if ($schedule->isMissed($now)) {
                ScheduleMissed::dispatch($schedule);

                $schedule->skip($now);

                return;
            }

            ScheduleTriggered::dispatch($schedule);

            $schedule->advance($now);
        });

        return self::SUCCESS;
    }
}
