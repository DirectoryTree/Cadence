<?php

namespace DirectoryTree\Cadence\Drivers;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DirectoryTree\Cadence\ScheduleDriver;
use DirectoryTree\Cadence\Support\RruleExpression;
use RRule\RRule;

class RruleSchedule implements ScheduleDriver
{
    /**
     * The RRULE expression.
     */
    protected string $expression;

    /**
     * The timezone for the schedule.
     */
    protected ?string $timezone = null;

    /**
     * Create a new RRULE schedule instance.
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
        $rrule = new RRule($this->toRfcString());

        $from = $this->timezone
            ? $after->setTimezone($this->timezone)
            : $after;

        $occurrences = $rrule->getOccurrencesAfter($from, false, 1);

        return ! empty($occurrences)
            ? Carbon::instance($occurrences[0])
            : null;
    }

    /**
     * Convert the expression to RFC-compliant format.
     *
     * DTSTART must be a separate property, not part of RRULE.
     */
    protected function toRfcString(): string
    {
        [$expression, $dtstart] = RruleExpression::extractDtstart($this->expression);

        if ($dtstart) {
            $tzid = $this->timezone ? ";TZID={$this->timezone}" : '';

            return "DTSTART{$tzid}:{$dtstart}\nRRULE:{$expression}";
        }

        return $expression;
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
