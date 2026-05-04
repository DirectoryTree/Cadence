<?php

namespace DirectoryTree\Cadence\Drivers;

use Carbon\CarbonInterface;
use Closure;
use DirectoryTree\Cadence\ScheduleDriver;

abstract class Schedule implements ScheduleDriver
{
    /**
     * The schedule expression.
     */
    protected string $expression;

    /**
     * The timezone for the schedule.
     */
    protected ?string $timezone = null;

    /**
     * The tap callback for configuring the underlying implementation.
     */
    protected static ?Closure $tapUsing = null;

    /**
     * Create a new schedule instance.
     */
    public function __construct(string $expression, ?string $timezone = null)
    {
        $this->expression = $expression;
        $this->timezone = $timezone;
    }

    /**
     * Register a callback to configure the underlying implementation.
     */
    public static function tap(?Closure $callback): void
    {
        static::$tapUsing = $callback;
    }

    /**
     * Resolve the next occurrence after the given date.
     */
    abstract protected function resolveNextOccurrence(CarbonInterface $after): ?CarbonInterface;

    /**
     * Get the next occurrence after the given date.
     */
    public function getNextOccurrence(CarbonInterface $after): ?CarbonInterface
    {
        if ($this->timezone) {
            $after = $after->setTimezone($this->timezone);
        }

        return $this->resolveNextOccurrence($after);
    }

    /**
     * Set the timezone.
     */
    public function setTimezone(string $timezone): static
    {
        $this->timezone = $timezone;

        return $this;
    }

    /**
     * Get the timezone for the schedule.
     */
    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    /**
     * Serialize the schedule to a storable expression.
     */
    public function toExpression(): string
    {
        return $this->expression;
    }

    /**
     * Reconstitute a schedule from a stored expression.
     */
    public static function fromExpression(string $expression): static
    {
        return new static($expression);
    }
}
