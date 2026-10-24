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
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::updating(function (self $schedule) {
            if ($schedule->isDirty('timezone')) {
                $schedule->next_run_at = $schedule->isEnabled()
                    ? $schedule->nextRunAt(now())
                    : null;
            }
        });
    }

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
            'next_run_at' => $this->nextRunAt(now()),
        ])->save();

        return $this;
    }

    /**
     * Advance the schedule to its next occurrence.
     */
    public function advance(CarbonInterface $date): static
    {
        $this->fill([
            'last_run_at' => $date->avoidMutation()
                ->setTimezone(config('app.timezone')),
            'next_run_at' => $this->nextRunAt($date),
        ])->save();

        return $this;
    }

    /**
     * Calculate the next run in the application's timezone.
     */
    protected function nextRunAt(CarbonInterface $after): ?CarbonInterface
    {
        return $this->toDriver()
            ->getNextOccurrence($after)
            ?->setTimezone(config('app.timezone'));
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
