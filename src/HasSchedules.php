<?php

namespace DirectoryTree\Cadence;

use DirectoryTree\Cadence\Drivers\ScheduleDriver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @mixin Model
 */
trait HasSchedules
{
    /**
     * Get the model's schedules.
     */
    public function schedules(): MorphMany
    {
        return $this->morphMany(Schedule::class, 'schedulable');
    }

    /**
     * Add a schedule to the model.
     */
    public function addSchedule(ScheduleDriver $driver): Schedule
    {
        return $this->schedules()->create([
            'type' => Cadence::getDriverType($driver),
            'expression' => $driver->toExpression(),
            'timezone' => $driver->getTimezone(),
            'next_run_at' => $driver->getNextOccurrence(now()),
        ]);
    }
}
