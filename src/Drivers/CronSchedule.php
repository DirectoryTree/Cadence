<?php

namespace DirectoryTree\Cadence\Drivers;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Cron\CronExpression;
use DirectoryTree\Cadence\ScheduleDriver;

class CronSchedule implements ScheduleDriver
{
    /**
     * The cron expression.
     */
    protected string $expression;

    /**
     * The timezone for the cron schedule.
     */
    protected ?string $timezone = null;

    /**
     * Create a new cron schedule instance.
     */
    public function __construct(string $expression, ?string $timezone = null)
    {
        $this->expression = $expression;
        $this->timezone = $timezone;
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
     * Get the next occurrence after the given date.
     */
    public function getNextOccurrence(CarbonInterface $after): ?CarbonInterface
    {
        $cron = new CronExpression($this->expression);

        $from = $this->timezone
            ? $after->setTimezone($this->timezone)
            : $after;

        return Carbon::instance($cron->getNextRunDate($from));
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
