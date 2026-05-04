<?php

namespace DirectoryTree\Cadence\Drivers;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Cron\CronExpression;

class CronSchedule extends Schedule
{
    /**
     * Resolve the next occurrence after the given date.
     */
    protected function resolveNextOccurrence(CarbonInterface $after): ?CarbonInterface
    {
        $cron = new CronExpression($this->expression);

        if (static::$tapUsing) {
            (static::$tapUsing)($cron, $this);
        }

        return Carbon::instance($cron->getNextRunDate($after));
    }
}
