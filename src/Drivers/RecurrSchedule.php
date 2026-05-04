<?php

namespace DirectoryTree\Cadence\Drivers;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTime;
use DateTimeZone;
use DirectoryTree\Cadence\Support\RruleExpression;
use Recurr\Rule;
use Recurr\Transformer\ArrayTransformer;
use Recurr\Transformer\Constraint\AfterConstraint;

class RecurrSchedule extends Schedule
{
    /**
     * Resolve the next occurrence after the given date.
     */
    protected function resolveNextOccurrence(CarbonInterface $after): ?CarbonInterface
    {
        [$expression, $dtstart] = RruleExpression::extractDtstart($this->expression);

        $startDate = $dtstart
            ? new DateTime($dtstart, $this->timezone ? new DateTimeZone($this->timezone) : null)
            : null;

        $rule = new Rule($expression, $startDate, null, $this->timezone);

        $transformer = new ArrayTransformer;

        if (static::$tapUsing) {
            (static::$tapUsing)($rule, $transformer, $this);
        }

        $recurrences = $transformer->transform(
            $rule, new AfterConstraint($after->toDateTime())
        );

        if ($first = $recurrences->first()) {
            return Carbon::instance($first->getStart());
        }

        return null;
    }
}
