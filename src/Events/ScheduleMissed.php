<?php

namespace DirectoryTree\Cadence\Events;

use DirectoryTree\Cadence\Schedule;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ScheduleMissed
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Schedule $schedule
    ) {}
}
