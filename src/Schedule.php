<?php

namespace DirectoryTree\Cadence;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use InvalidArgumentException;

class Schedule extends Model
{
    /**
     * The registered schedule drivers.
     *
     * @var array<string, class-string<ScheduleDriver>>
     */
    protected static array $drivers = [];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'expression',
        'timezone',
        'next_run_at',
        'last_run_at',
    ];

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
     * Register a schedule driver.
     *
     * @param  class-string<ScheduleDriver>  $driverClass
     */
    public static function driver(string $type, string $driverClass): void
    {
        static::$drivers[$type] = $driverClass;
    }

    /**
     * Resolve the type string for a driver instance.
     */
    public static function resolveDriverType(ScheduleDriver $driver): string
    {
        $type = array_search(get_class($driver), static::$drivers, true);

        if ($type === false) {
            throw new InvalidArgumentException(
                'Unregistered schedule driver: '.get_class($driver)
            );
        }

        return $type;
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
        $driverClass = static::$drivers[$this->type] ?? null;

        if (! $driverClass) {
            throw new InvalidArgumentException(
                "Unknown schedule driver type: {$this->type}"
            );
        }

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
        $query->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', $date ?? now());
    }
}
