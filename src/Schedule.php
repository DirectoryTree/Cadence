<?php

namespace DirectoryTree\Cadence;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonInterval;
use DateInterval;
use DateTimeInterface;
use DirectoryTree\Cadence\Drivers\ScheduleDriver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use InvalidArgumentException;
use LogicException;

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
            'max_delay' => 'integer',
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
     * Skip the schedule to its next occurrence without recording a run.
     */
    public function skip(CarbonInterface $date): static
    {
        $this->fill([
            'next_run_at' => $this->toDriver()->getNextOccurrence($date),
        ])->save();

        return $this;
    }

    /**
     * Limit how late the schedule may run, as an interval or a deadline.
     */
    public function noLaterThan(DateInterval|DateTimeInterface $limit): static
    {
        if ($limit instanceof DateInterval) {
            $seconds = (int) CarbonInterval::instance($limit)->totalSeconds;

            if ($seconds < 0) {
                throw new InvalidArgumentException('The maximum delay must not be negative.');
            }
        } else {
            $next = $this->next_run_at ?? $this->toDriver()->getNextOccurrence(now());

            if (! $next) {
                throw new LogicException('Cannot resolve a maximum delay from a date for a schedule without a next occurrence.');
            }

            $deadline = CarbonImmutable::instance($limit);

            if ($deadline->lessThanOrEqualTo($next)) {
                $deadline = $deadline->addDays((int) $deadline->diffInDays($next));

                while ($deadline->lessThanOrEqualTo($next)) {
                    $deadline = $deadline->addDay();
                }
            }

            $seconds = $deadline->getTimestamp() - $next->getTimestamp();
        }

        $this->fill(['max_delay' => $seconds])->save();

        return $this;
    }

    /**
     * Determine if the schedule's due occurrence is later than its maximum delay.
     */
    public function isMissed(?CarbonInterface $date = null): bool
    {
        if ($this->max_delay === null || $this->next_run_at === null) {
            return false;
        }

        return ($date ?? now())->getTimestamp() - $this->next_run_at->getTimestamp() > $this->max_delay;
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
