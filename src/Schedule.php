<?php

namespace DirectoryTree\Cadence;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
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
        ];
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
        $driverClass = Cadence::getDriver($this->type);

        $driver = $driverClass::fromExpression($this->expression);

        if ($this->timezone) {
            $driver->setTimezone($this->timezone);
        }

        return $driver;
    }

    /**
     * Get the next occurrence after the given date.
     */
    public function nextOccurrence(CarbonInterface $after): ?CarbonInterface
    {
        return $this->toDriver()->getNextOccurrence($after);
    }

    /**
     * Scope to schedules that are due.
     */
    public function scopeDue(Builder $query, ?CarbonInterface $date = null): void
    {
        $query->where(function (Builder $query) use ($date) {
            $query
                ->whereNotNull('next_run_at')
                ->where('next_run_at', '<=', $date ?? now());
        });
    }
}
