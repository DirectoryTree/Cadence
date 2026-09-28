<?php

namespace DirectoryTree\Cadence;

use Carbon\CarbonInterface;
use DirectoryTree\Cadence\Drivers\ScheduleDriver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Schedule extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'next_run_at' => 'datetime',
            'last_run_at' => 'datetime',
            'disabled_at' => 'datetime',
        ];
    }

    /**
     * Create a new Eloquent query builder for the model.
     */
    public function newEloquentBuilder($query): Builders\ScheduleBuilder
    {
        return new Builders\ScheduleBuilder($query);
    }

    /**
     * Convert a DateTime to a storable string in the application's timezone.
     *
     * Eloquent stores a date's wall-clock time without converting it, then reads it back
     * in the default timezone. Converting first means a date in another timezone (such
     * as a driver occurrence in the schedule's timezone) keeps the same moment.
     */
    public function fromDateTime($value)
    {
        return empty($value) ? $value : $this->asDateTime($value)
            ->setTimezone(date_default_timezone_get())
            ->format($this->getDateFormat());
    }

    /**
     * Determine if the schedule is disabled.
     */
    public function isDisabled(): bool
    {
        return (bool) $this->disabled_at;
    }

    /**
     * Determine if the schedule is enabled.
     */
    public function isEnabled(): bool
    {
        return ! $this->isDisabled();
    }

    /**
     * Disable the schedule.
     */
    public function disable(): static
    {
        $this->fill([
            'disabled_at' => now(),
            'next_run_at' => null,
        ])->save();

        return $this;
    }

    /**
     * Enable the schedule.
     */
    public function enable(): static
    {
        $this->fill([
            'disabled_at' => null,
            'next_run_at' => $this->toDriver()->getNextOccurrence(now()),
        ])->save();

        return $this;
    }

    /**
     * Advance the schedule to its next occurrence.
     */
    public function advance(CarbonInterface $date): static
    {
        $this->fill([
            'last_run_at' => $date,
            'next_run_at' => $this->toDriver()->getNextOccurrence($date),
        ])->save();

        return $this;
    }

    /**
     * Get the parent schedulable model.
     */
    public function schedulable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Resolve the schedule driver instance from the stored type and expression.
     */
    public function toDriver(): ScheduleDriver
    {
        /** @var class-string<ScheduleDriver> $driverClass */
        $driverClass = Cadence::getDriver($this->type);

        $driver = $driverClass::fromExpression($this->expression);

        if ($this->timezone) {
            $driver->setTimezone($this->timezone);
        }

        return $driver;
    }
}
