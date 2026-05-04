<?php

namespace DirectoryTree\Cadence;

use Illuminate\Database\Eloquent\Relations\MorphMany;

interface Schedulable
{
    /**
     * Get the model's schedules.
     */
    public function schedules(): MorphMany;

    /**
     * Add a schedule to the model.
     */
    public function addSchedule(ScheduleDriver $driver): Schedule;
}
