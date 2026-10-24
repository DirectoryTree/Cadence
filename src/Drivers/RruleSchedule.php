<?php

namespace DirectoryTree\Cadence\Drivers;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DirectoryTree\Cadence\Support\RruleExpression;
use RRule\RRule;

class RruleSchedule extends Schedule
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
        $rrule = new RRule($this->toRfcString());

        if ($callback = static::$tapCallbacks[static::class] ?? null) {
            $callback($rrule);
        }

        $occurrences = $rrule->getOccurrencesAfter($after, false, 1);

        if ($occurrences && $occurrence = $occurrences[0]) {
            return Carbon::instance($occurrence);
        }

        return null;
    }

    /**
     * Convert the expression to RFC-compliant format.
     *
     * DTSTART must be a separate property, not part of RRULE.
     */
    protected function toRfcString(): string
    {
        [$expression, $dtstart] = RruleExpression::extractDtstart($this->toExpression());

        if ($dtstart) {
            $tzid = $this->timezone && ! str_ends_with($dtstart, 'Z')
                ? ";TZID={$this->timezone}"
                : '';

            return "DTSTART{$tzid}:{$dtstart}\nRRULE:{$expression}";
        }

        return $expression;
    }
}
