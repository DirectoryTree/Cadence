<?php

namespace DirectoryTree\Cadence\Events;

use DirectoryTree\Cadence\Schedule;
use Illuminate\Foundation\Events\Dispatchable;

class ScheduleTriggered
{
    use Dispatchable;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Schedule $schedule
    ) {}
}
