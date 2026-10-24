<?php

namespace DirectoryTree\Cadence\Builders;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class ScheduleBuilder extends Builder
{
    /**
     * Scope to schedules that are due.
     */
    public function due(?CarbonInterface $date = null): Builder
    {
        return $this->where(function (Builder $query) use ($date) {
            $query
                ->whereNotNull('next_run_at')
                ->where('next_run_at', '<=', $date ?? now());
        });
    }

    /**
     * Scope to enabled schedules only.
     */
    public function enabled(): Builder
    {
        return $this->whereNull('disabled_at');
    }

    /**
     * Scope to disabled schedules only.
     */
    public function disabled(): Builder
    {
        return $this->whereNotNull('disabled_at');
    }
}
