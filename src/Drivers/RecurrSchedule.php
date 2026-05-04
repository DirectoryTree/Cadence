<?php

namespace DirectoryTree\Cadence\Drivers;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTime;
use DateTimeZone;
use DirectoryTree\Cadence\ScheduleDriver;
use DirectoryTree\Cadence\Support\RruleExpression;
use Recurr\Rule;
use Recurr\Transformer\ArrayTransformer;
use Recurr\Transformer\Constraint\AfterConstraint;

class RecurrSchedule implements ScheduleDriver
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
     * Create a new Recurr schedule instance.
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
        $from = $this->timezone
            ? $after->setTimezone($this->timezone)
            : $after;

        [$expression, $dtstart] = RruleExpression::extractDtstart($this->expression);

        $startDate = $dtstart
            ? new DateTime($dtstart, $this->timezone ? new DateTimeZone($this->timezone) : null)
            : null;

        $rule = new Rule($expression, $startDate, null, $this->timezone);

        $recurrences = (new ArrayTransformer)->transform(
            $rule, new AfterConstraint($from->toDateTime())
        );

        $first = $recurrences->first();

        return $first ? Carbon::instance($first->getStart()) : null;
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
