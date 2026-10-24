<?php

namespace DirectoryTree\Cadence\Events;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DirectoryTree\Cadence\Schedule;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ScheduleTriggered
{
    use Dispatchable, SerializesModels;

    /**
     * The schedule that was triggered.
     */
    public Schedule $schedule;

    /**
     * The time the occurrence was scheduled to run.
     */
    public CarbonImmutable $scheduledAt;

    /**
     * The time the occurrence was dispatched.
     */
    public CarbonImmutable $dispatchedAt;

    /**
     * Create a new event instance.
     */
    public function __construct(Schedule $schedule, ?CarbonInterface $dispatchedAt = null)
    {
        $this->schedule = $schedule;
        $this->scheduledAt = CarbonImmutable::instance($schedule->next_run_at);
        $this->dispatchedAt = CarbonImmutable::instance($dispatchedAt ?? now());
    }
}
