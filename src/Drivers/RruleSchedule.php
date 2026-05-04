<?php

namespace DirectoryTree\Cadence\Drivers;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DirectoryTree\Cadence\Support\RruleExpression;
use RRule\RRule;

class RruleSchedule extends Schedule
{
    /**
     * Resolve the next occurrence after the given date.
     */
    protected function resolveNextOccurrence(CarbonInterface $after): ?CarbonInterface
    {
        $rrule = new RRule($this->toRfcString());

        if (static::$tapUsing) {
            (static::$tapUsing)($rrule);
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
        [$expression, $dtstart] = RruleExpression::extractDtstart($this->expression);

        if ($dtstart) {
            $tzid = $this->timezone ? ";TZID={$this->timezone}" : '';

            return "DTSTART{$tzid}:{$dtstart}\nRRULE:{$expression}";
        }

        return $expression;
    }
}
