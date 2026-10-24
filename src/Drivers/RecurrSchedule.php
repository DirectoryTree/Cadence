<?php

namespace DirectoryTree\Cadence\Drivers;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTime;
use DateTimeZone;
use DirectoryTree\Cadence\Support\RecurrAfterConstraint;
use DirectoryTree\Cadence\Support\RruleExpression;
use Recurr\Rule;
use Recurr\Transformer\ArrayTransformer;

class RecurrSchedule extends Schedule
{
    /**
     * Serialize the expression with a stable start in the schedule's timezone.
     */
    public function toExpression(): string
    {
        if (! RruleExpression::hasDtstart($this->expression)) {
            $this->timezone ??= date_default_timezone_get();

            $this->expression .= ';DTSTART='.now($this->timezone)->format('Ymd\THis');
        }

        return $this->expression;
    }

    /**
     * Resolve the next occurrence after the given date.
     */
    protected function resolveNextOccurrence(CarbonInterface $after): ?CarbonInterface
    {
        $rule = $this->newRule();

        $transformer = $this->newTransformer();

        if (static::$tapUsing) {
            (static::$tapUsing)($rule, $transformer, $this);
        }

        $recurrences = $transformer->transform($rule, $this->newConstraint($after, $rule), false);

        if ($first = $recurrences->first()) {
            return Carbon::instance($first->getStart());
        }

        return null;
    }

    /**
     * Create a new recurrence rule instance.
     */
    protected function newRule(): Rule
    {
        [$expression, $dtstart] = RruleExpression::extractDtstart($this->toExpression());

        if ($dtstart) {
            $timezone = $this->timezone ? new DateTimeZone($this->timezone) : null;

            $startDate = new DateTime($dtstart, $timezone);
        }

        return new Rule($expression, $startDate ?? null, null, $this->timezone);
    }

    /**
     * Create a new constraint instance.
     */
    protected function newConstraint(CarbonInterface $after, Rule $rule): RecurrAfterConstraint
    {
        return new RecurrAfterConstraint(
            $after->toDateTime(),
            $rule->getCount(),
            $rule->getExDates()
        );
    }

    /**
     * Create a new transformer instance.
     */
    protected function newTransformer(): ArrayTransformer
    {
        return new ArrayTransformer;
    }
}
