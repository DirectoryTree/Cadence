<?php

namespace DirectoryTree\Cadence\Drivers;

use Carbon\CarbonInterface;

interface ScheduleDriver
{
    /**
     * Reconstitute a schedule from a stored expression.
     */
    public static function fromExpression(string $expression): static;

    /**
     * Set the timezone for the schedule.
     */
    public function setTimezone(string $timezone): static;

    /**
     * Get the timezone for the schedule.
     */
    public function getTimezone(): ?string;

    /**
     * Serialize the schedule to a storable expression.
     */
    public function toExpression(): string;

    /**
     * Get the next occurrence after the given date.
     */
    public function getNextOccurrence(CarbonInterface $after): ?CarbonInterface;
}
