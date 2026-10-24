<?php

namespace DirectoryTree\Cadence\Support;

use DateTimeImmutable;
use DateTimeInterface;
use Recurr\DateExclusion;
use Recurr\Transformer\Constraint\AfterConstraint;

class RecurrAfterConstraint extends AfterConstraint
{
    /**
     * The number of occurrences evaluated since the rule's start.
     */
    protected int $occurrences = 0;

    /**
     * The maximum number of occurrences in the rule.
     */
    protected ?int $count;

    /**
     * The dates excluded from the rule.
     *
     * @var list<DateExclusion>
     */
    protected array $exclusions;

    /**
     * Whether a usable occurrence has been found.
     */
    protected bool $found = false;

    /**
     * Create a constraint that finds the first usable occurrence and preserves COUNT.
     */
    public function __construct(DateTimeInterface $after, ?int $count = null, array $exclusions = [])
    {
        parent::__construct($after);

        $this->count = $count;
        $this->exclusions = $exclusions;
    }

    /**
     * Determine if the occurrence is the first usable date within the rule's count.
     */
    public function test(DateTimeInterface $date): bool
    {
        if ($this->found || ($this->count !== null && ++$this->occurrences > $this->count)) {
            $this->stopsTransformer = true;

            return false;
        }

        if (! parent::test($date) || $this->isExcluded($date)) {
            return false;
        }

        $this->found = true;

        return true;
    }

    /**
     * Determine if the date is excluded from the rule.
     */
    protected function isExcluded(DateTimeInterface $date): bool
    {
        $date = DateTimeImmutable::createFromInterface($date);

        foreach ($this->exclusions as $exclusion) {
            $format = $exclusion->hasTime ? 'Ymd\\THis' : 'Ymd';

            if ($date->setTimezone($exclusion->date->getTimezone())->format($format) === $exclusion->date->format($format)) {
                return true;
            }
        }

        return false;
    }
}
